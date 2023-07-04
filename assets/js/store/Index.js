Ext.define('GibsonOS.module.archivist.store.Index', {
    extend: 'GibsonOS.data.Store',
    alias: ['store.gosModuleArchivistIndexStore'],
    pageSize: 100,
    model: 'GibsonOS.module.archivist.model.Index',
    constructor(data) {
        let me = this;

        me.proxy = {
            type: 'gosDataProxyAjax',
            url: baseDir + 'archivist/index',
            method: 'GET',
            extraParams: {
                accountId: data.accountId,
                ruleId: data.ruleId
            }
        };

        me.callParent(arguments);
    }
});