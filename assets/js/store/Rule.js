Ext.define('GibsonOS.module.archivist.store.Rule', {
    extend: 'GibsonOS.data.Store',
    alias: ['store.gosModuleArchivistRuleStore'],
    pageSize: 100,
    model: 'GibsonOS.module.archivist.model.Rule',
    constructor(data) {
        let me = this;

        me.proxy = {
            type: 'gosDataProxyAjax',
            url: baseDir + 'archivist/rule/index',
            extraParams: {
                accountId: data.accountId
            }
        };

        me.callParent(arguments);
    }
});