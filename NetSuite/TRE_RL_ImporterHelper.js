/**
 * @NApiVersion 2.1
 * @NScriptType Restlet
 */
define(['N/record', 'N/search', 'N/error', 'N/log'], (record, search, error, log) => {
    const SEARCH_PAGE_SIZE = 1000;

    const post = (requestBody) => {
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
                throwBadRequest(`Unsupported POST action [${action}].`);
        }
    };

    const put = (requestBody) => {
        throwBadRequest('Unsupported HTTP method [PUT]. Use POST with action in body.');
    };

    const get = (requestParams) => {
        throwBadRequest('Unsupported HTTP method [GET]. Use POST with action in body.');
    };

    const createRecord = (payload) => {
        const recordType = requireString(payload, 'recordType');
        const bodyFields = asObject(payload.fields, 'fields');
        const sublists = asObject(payload.sublists || {}, 'sublists');

        const nsRecord = record.create({
            type: recordType,
            isDynamic: true,
        });

        applyBodyFields(nsRecord, bodyFields);
        applySublists(nsRecord, sublists);

        const id = nsRecord.save({
            enableSourcing: true,
            ignoreMandatoryFields: false,
        });

        return {
            ok: true,
            action: 'createRecord',
            recordType,
            id: String(id),
        };
    };

    const createTransaction = (payload) => {
        const recordType = String(payload.type || 'salesorder').trim();
        const bodyParams = asObject(payload.bodyParams, 'bodyParams');
        const lineParams = payload.lineParams;
        const isDynamic = payload.isDynamic !== false;

        if (!Array.isArray(lineParams) || lineParams.length === 0) {
            throwBadRequest('Parameter [lineParams] must be a non-empty array.');
        }

        const nsRecord = record.create({
            type: recordType,
            isDynamic,
        });

        applyBodyFields(nsRecord, bodyParams);
        applySublists(nsRecord, { item: lineParams });

        const id = nsRecord.save({
            enableSourcing: true,
            ignoreMandatoryFields: false,
        });

        return {
            ok: true,
            action: 'createTransaction',
            type: recordType,
            id: String(id),
        };
    };

    const updateRecord = (payload) => {
        const recordType = requireString(payload, 'recordType');
        const id = requireString(payload, 'id');
        const bodyFields = asObject(payload.fields || {}, 'fields');
        const sublists = asObject(payload.sublists || {}, 'sublists');

        const nsRecord = record.load({
            type: recordType,
            id,
            isDynamic: true,
        });

        applyBodyFields(nsRecord, bodyFields);
        applySublists(nsRecord, sublists);

        const savedId = nsRecord.save({
            enableSourcing: true,
            ignoreMandatoryFields: false,
        });

        return {
            ok: true,
            action: 'updateRecord',
            recordType,
            id: String(savedId),
        };
    };

    const getRecord = (params) => {
        const recordType = requireString(params, 'recordType');
        const id = requireString(params, 'id');
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

        return {
            ok: true,
            action: 'getRecord',
            recordType,
            id: String(id),
            fields: body,
        };
    };

    const getRecordFields = (params) => {
        const recordType = requireString(params, 'recordType');
        const id = requireString(params, 'id');
        const fields = normalizeFieldsParam(params.fields);

        if (fields.length === 0) {
            throwBadRequest('Parameter [fields] must include at least one field id.');
        }

        const values = search.lookupFields({
            type: recordType,
            id,
            columns: fields,
        });

        return {
            ok: true,
            action: 'getRecordFields',
            recordType,
            id: String(id),
            fields: values || {},
        };
    };

    const runSavedSearch = (payload) => {
        const searchId = requireString(payload, 'searchId');
        const searchType = requireString(payload, 'type');

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

        return {
            ok: true,
            action: 'runSavedSearch',
            searchId,
            type: searchType,
            count: results.length,
            results,
        };
    };

    const applyBodyFields = (nsRecord, fields) => {
        Object.keys(fields).forEach((fieldId) => {
            nsRecord.setValue({
                fieldId,
                value: fields[fieldId],
            });
        });
    };

    const applySublists = (nsRecord, sublists) => {
        Object.keys(sublists).forEach((sublistId) => {
            const lines = sublists[sublistId];
            if (!Array.isArray(lines)) {
                throwBadRequest(`Sublist [${sublistId}] must be an array of line objects.`);
            }

            lines.forEach((line) => {
                if (!isObject(line)) {
                    throwBadRequest(`Each line in sublist [${sublistId}] must be an object.`);
                }

                nsRecord.selectNewLine({ sublistId });

                Object.keys(line).forEach((fieldId) => {
                    nsRecord.setCurrentSublistValue({
                        sublistId,
                        fieldId,
                        value: line[fieldId],
                    });
                });

                nsRecord.commitLine({ sublistId });
            });
        });
    };

    const requireString = (source, key) => {
        const value = source && source[key];
        if (typeof value !== 'string' || value.trim() === '') {
            throwBadRequest(`Missing required parameter [${key}].`);
        }

        return value.trim();
    };

    const normalizeFieldsParam = (raw) => {
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
    };

    const asObject = (value, keyName) => {
        if (!isObject(value)) {
            throwBadRequest(`Parameter [${keyName}] must be an object.`);
        }

        return value;
    };

    const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

    const throwBadRequest = (message) => {
        log.error({ title: 'TRE_RL_ImporterHelper bad request', details: message });

        throw error.create({
            name: 'TRE_BAD_REQUEST',
            message,
            notifyOff: true,
        });
    };

    return { get, post, put };
});

