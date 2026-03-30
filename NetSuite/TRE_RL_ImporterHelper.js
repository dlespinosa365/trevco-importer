/**
 * @NApiVersion 2.1
 * @NScriptType Restlet
 */
define(['N/record', 'N/search', 'N/error', 'N/log'], (record, search, error, log) => {
    const SEARCH_PAGE_SIZE = 1000;

    const MAX_LOG_PAYLOAD_CHARS = 120000;

    /**
     * {@link record.create} must always use standard (non-dynamic) mode for this RESTlet.
     * Creates use {@link Helper.applySublistsStandard} (insertLine / setSublistValue). Payloads cannot override this.
     */
    const IS_DYNAMIC_FOR_RECORD_CREATE = false;

    /**
     * Shared validation, record helpers, and structured logging (debug + audit).
     */
    const Helper = {
        /**
         * @param {object} value
         * @returns {boolean}
         */
        isObject(value) {
            return value !== null && typeof value === 'object' && !Array.isArray(value);
        },

        /**
         * Safe JSON for logs (length-capped for NetSuite execution log limits).
         *
         * @param {*} payload
         * @returns {string}
         */
        serializePayloadForLog(payload) {
            try {
                const s = JSON.stringify(payload === undefined ? null : payload);
                if (s.length > MAX_LOG_PAYLOAD_CHARS) {
                    return `${s.slice(0, MAX_LOG_PAYLOAD_CHARS)}...[truncated ${s.length - MAX_LOG_PAYLOAD_CHARS} chars]`;
                }

                return s;
            } catch (e) {
                return `[serialize error: ${e && e.message ? e.message : e}]`;
            }
        },

        /**
         * Debug + audit trail for every inbound POST body.
         *
         * @param {object} [requestBody]
         */
        logInboundPost(requestBody) {
            const payload = requestBody || {};
            const action = String(payload.action || '').trim() || '(no action)';
            const serialized = Helper.serializePayloadForLog(payload);

            log.debug({
                title: 'TRE_RL_ImporterHelper POST',
                details: `action=${action} payload=${serialized}`,
            });
            log.audit({
                title: 'TRE_RL_ImporterHelper audit: inbound POST',
                details: `action=${action} payload=${serialized}`,
            });
        },

        /**
         * Log unsupported HTTP methods (still a request worth auditing).
         *
         * @param {string} method
         * @param {*} bodyOrParams
         */
        logUnsupportedMethod(method, bodyOrParams) {
            const serialized = Helper.serializePayloadForLog(bodyOrParams || {});
            log.debug({
                title: `TRE_RL_ImporterHelper ${method}`,
                details: serialized,
            });
            log.audit({
                title: `TRE_RL_ImporterHelper audit: ${method} (rejected)`,
                details: serialized,
            });
        },

        /**
         * Audit line after a successful handler (compact summary; not full response body).
         *
         * @param {string} action
         * @param {object} summary
         */
        logActionOutcome(action, summary) {
            const details = Helper.serializePayloadForLog({ action, ...summary });
            log.debug({
                title: `TRE_RL_ImporterHelper OK: ${action}`,
                details,
            });
            log.audit({
                title: `TRE_RL_ImporterHelper audit: ${action} success`,
                details,
            });
        },

        /**
         * @param {object} source
         * @param {string} key
         * @returns {string}
         */
        requireString(source, key) {
            const value = source && source[key];
            if (typeof value !== 'string' || value.trim() === '') {
                Helper.throwBadRequest(`Missing required parameter [${key}].`);
            }

            return value.trim();
        },

        /**
         * @param {*} raw
         * @returns {string[]}
         */
        normalizeFieldsParam(raw) {
            if (Array.isArray(raw)) {
                return raw
                    .map((v) => (typeof v === 'string' ? v.trim() : ''))
                    .filter(Boolean);
            }

            if (typeof raw === 'string') {
                return raw
                    .split(',')
                    .map((v) => v.trim())
                    .filter(Boolean);
            }

            return [];
        },

        /**
         * @param {*} value
         * @param {string} keyName
         * @returns {object}
         */
        asObject(value, keyName) {
            if (!Helper.isObject(value)) {
                Helper.throwBadRequest(`Parameter [${keyName}] must be an object.`);
            }

            return value;
        },

        /**
         * @param {string} message
         * @returns {never}
         */
        throwBadRequest(message) {
            log.error({ title: 'TRE_RL_ImporterHelper bad request', details: message });
            log.audit({
                title: 'TRE_RL_ImporterHelper audit: bad request',
                details: message,
            });

            throw error.create({
                name: 'TRE_BAD_REQUEST',
                message,
                notifyOff: true,
            });
        },

        /**
         * N/record {@link record.Record#setValue} expects select/reference internal IDs as scalars.
         * SuiteTalk REST often uses `{ id: "123" }`; unwrap so saves do not throw INVALID_FLD_VALUE.
         *
         * @param {*} value
         * @returns {*}
         */
        normalizeReferenceValueForRecord(value) {
            if (!Helper.isObject(value)) {
                return value;
            }

            const keys = Object.keys(value);
            if (keys.length === 1 && keys[0] === 'id') {
                return value.id;
            }

            return value;
        },

        /**
         * @param {Record} nsRecord
         * @param {object} fields
         */
        applyBodyFields(nsRecord, fields) {
            Object.keys(fields).forEach((fieldId) => {
                nsRecord.setValue({
                    fieldId,
                    value: Helper.normalizeReferenceValueForRecord(fields[fieldId]),
                });
            });
        },

        /**
         * Standard (non-dynamic) mode: {@link record.Record#insertLine} + {@link record.Record#setSublistValue}.
         * Use with {@link record.create} when `isDynamic` is false — `selectNewLine` / `commitLine` are not used.
         *
         * @param {Record} nsRecord
         * @param {object} sublists
         */
        applySublistsStandard(nsRecord, sublists) {
            Object.keys(sublists).forEach((sublistId) => {
                const lines = sublists[sublistId];
                if (!Array.isArray(lines)) {
                    Helper.throwBadRequest(`Sublist [${sublistId}] must be an array of line objects.`);
                }

                lines.forEach((line) => {
                    if (!Helper.isObject(line)) {
                        Helper.throwBadRequest(`Each line in sublist [${sublistId}] must be an object.`);
                    }

                    const lineIndex = nsRecord.getLineCount({ sublistId });
                    nsRecord.insertLine({ sublistId, line: lineIndex });

                    Object.keys(line).forEach((fieldId) => {
                        nsRecord.setSublistValue({
                            sublistId,
                            fieldId,
                            line: lineIndex,
                            value: Helper.normalizeReferenceValueForRecord(line[fieldId]),
                        });
                    });
                });
            });
        },

        /**
         * Dynamic mode: {@link record.Record#selectNewLine} + {@link record.Record#setCurrentSublistValue} + {@link record.Record#commitLine}.
         * Use with {@link record.load} when the record was opened in dynamic mode.
         *
         * @param {Record} nsRecord
         * @param {object} sublists
         */
        applySublistsDynamic(nsRecord, sublists) {
            Object.keys(sublists).forEach((sublistId) => {
                const lines = sublists[sublistId];
                if (!Array.isArray(lines)) {
                    Helper.throwBadRequest(`Sublist [${sublistId}] must be an array of line objects.`);
                }

                lines.forEach((line) => {
                    if (!Helper.isObject(line)) {
                        Helper.throwBadRequest(`Each line in sublist [${sublistId}] must be an object.`);
                    }

                    nsRecord.selectNewLine({ sublistId });

                    Object.keys(line).forEach((fieldId) => {
                        nsRecord.setCurrentSublistValue({
                            sublistId,
                            fieldId,
                            value: Helper.normalizeReferenceValueForRecord(line[fieldId]),
                        });
                    });

                    nsRecord.commitLine({ sublistId });
                });
            });
        },
    };

    const post = (requestBody) => {
        Helper.logInboundPost(requestBody);

        const payload = requestBody || {};
        const action = String(payload.action || '').trim();

        switch (action) {
            case 'createRecord':
                return createRecord(payload);
            case 'createTransaction':
                return createTransaction(payload);
            case 'updateRecord':
                return updateRecord(payload);
            case 'getRecord':
                return getRecord(payload);
            case 'getRecordFields':
                return getRecordFields(payload);
            case 'runSavedSearch':
                return runSavedSearch(payload);
            default:
                Helper.throwBadRequest(`Unsupported POST action [${action}].`);
        }
    };

    const put = (requestBody) => {
        Helper.logUnsupportedMethod('PUT', requestBody);
        Helper.throwBadRequest('Unsupported HTTP method [PUT]. Use POST with action in body.');
    };

    const get = (requestParams) => {
        Helper.logUnsupportedMethod('GET', requestParams);
        Helper.throwBadRequest('Unsupported HTTP method [GET]. Use POST with action in body.');
    };

    const createRecord = (payload) => {
        const recordType = Helper.requireString(payload, 'recordType');
        const bodyFields = Helper.asObject(payload.fields, 'fields');
        const sublists = Helper.asObject(payload.sublists || {}, 'sublists');

        const nsRecord = record.create({
            type: recordType,
            isDynamic: IS_DYNAMIC_FOR_RECORD_CREATE,
        });

        Helper.applyBodyFields(nsRecord, bodyFields);
        Helper.applySublistsStandard(nsRecord, sublists);

        const id = nsRecord.save({
            enableSourcing: true,
            ignoreMandatoryFields: false,
        });

        const result = {
            ok: true,
            action: 'createRecord',
            recordType,
            id: String(id),
        };
        Helper.logActionOutcome('createRecord', { recordType, id: result.id });

        return result;
    };

    const createTransaction = (payload) => {
        const recordType = String(payload.type || 'salesorder').trim();
        const bodyParams = Helper.asObject(payload.bodyParams, 'bodyParams');
        const lineParams = payload.lineParams;

        if (!Array.isArray(lineParams) || lineParams.length === 0) {
            Helper.throwBadRequest('Parameter [lineParams] must be a non-empty array.');
        }

        const nsRecord = record.create({
            type: recordType,
            isDynamic: IS_DYNAMIC_FOR_RECORD_CREATE,
        });

        Helper.applyBodyFields(nsRecord, bodyParams);
        Helper.applySublistsStandard(nsRecord, { item: lineParams });

        const id = nsRecord.save({
            enableSourcing: true,
            ignoreMandatoryFields: false,
        });

        const result = {
            ok: true,
            action: 'createTransaction',
            type: recordType,
            id: String(id),
        };
        Helper.logActionOutcome('createTransaction', { type: recordType, id: result.id });

        return result;
    };

    const updateRecord = (payload) => {
        const recordType = Helper.requireString(payload, 'recordType');
        const id = Helper.requireString(payload, 'id');
        const bodyFields = Helper.asObject(payload.fields || {}, 'fields');
        const sublists = Helper.asObject(payload.sublists || {}, 'sublists');

        const nsRecord = record.load({
            type: recordType,
            id,
            isDynamic: true,
        });

        Helper.applyBodyFields(nsRecord, bodyFields);
        Helper.applySublistsDynamic(nsRecord, sublists);

        const savedId = nsRecord.save({
            enableSourcing: true,
            ignoreMandatoryFields: false,
        });

        const result = {
            ok: true,
            action: 'updateRecord',
            recordType,
            id: String(savedId),
        };
        Helper.logActionOutcome('updateRecord', { recordType, id: result.id });

        return result;
    };

    const getRecord = (params) => {
        const recordType = Helper.requireString(params, 'recordType');
        const id = Helper.requireString(params, 'id');
        const fieldsCsv = String(params.fields || '').trim();
        const fieldIds = fieldsCsv === '' ? [] : fieldsCsv.split(',').map((f) => f.trim()).filter(Boolean);

        const nsRecord = record.load({
            type: recordType,
            id,
            isDynamic: false,
        });

        const body = {};
        if (fieldIds.length === 0) {
            nsRecord.getFields().forEach((fieldId) => {
                body[fieldId] = nsRecord.getValue({ fieldId });
            });
        } else {
            fieldIds.forEach((fieldId) => {
                body[fieldId] = nsRecord.getValue({ fieldId });
            });
        }

        const result = {
            ok: true,
            action: 'getRecord',
            recordType,
            id: String(id),
            fields: body,
        };
        Helper.logActionOutcome('getRecord', { recordType, id: result.id, fieldCount: Object.keys(body).length });

        return result;
    };

    const getRecordFields = (params) => {
        const recordType = Helper.requireString(params, 'recordType');
        const id = Helper.requireString(params, 'id');
        const fields = Helper.normalizeFieldsParam(params.fields);

        if (fields.length === 0) {
            Helper.throwBadRequest('Parameter [fields] must include at least one field id.');
        }

        const values = search.lookupFields({
            type: recordType,
            id,
            columns: fields,
        });

        const result = {
            ok: true,
            action: 'getRecordFields',
            recordType,
            id: String(id),
            fields: values || {},
        };
        Helper.logActionOutcome('getRecordFields', { recordType, id: result.id, requestedFields: fields.length });

        return result;
    };

    const runSavedSearch = (payload) => {
        const searchId = Helper.requireString(payload, 'searchId');
        const searchType = Helper.requireString(payload, 'type');

        const loadedSearch = search.load({
            id: searchId,
            type: searchType,
        });

        const pagedData = loadedSearch.runPaged({
            pageSize: SEARCH_PAGE_SIZE,
        });

        const results = [];

        pagedData.pageRanges.forEach((pageRange) => {
            const page = pagedData.fetch({
                index: pageRange.index,
            });

            page.data.forEach((result) => {
                const row = {};
                loadedSearch.columns.forEach((column) => {
                    const key = (column.label || column.name || '').trim();
                    if (!key) {
                        return;
                    }

                    const text = result.getText(column);
                    const value = result.getValue(column);
                    row[key] = text !== null && text !== '' ? text : value;
                });

                results.push(row);
            });
        });

        const result = {
            ok: true,
            action: 'runSavedSearch',
            searchId,
            type: searchType,
            count: results.length,
            results,
        };
        Helper.logActionOutcome('runSavedSearch', {
            searchId,
            type: searchType,
            rowCount: results.length,
        });

        return result;
    };

    return { get, post, put };
});
