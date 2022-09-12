Ext.define('GibsonOS.module.archivist.store.Index', {
    extend: 'GibsonOS.data.Store',
    alias: ['store.gosModuleArchivistIndexStore'],
    pageSize: 100,
    model: 'GibsonOS.module.archivist.model.Index',
    constructor(data) {
        let me = this;

        me.proxy = {
            type: 'gosDataProxyAjax',
            url: baseDir + 'archivist/index/index',
            extraParams: {
                ruleId: data.ruleId
            }
        };

        me.callParent(arguments);
    }
});