Ext.define('GibsonOS.module.archivist.rule.store.Grid', {
    extend: 'GibsonOS.data.Store',
    alias: ['store.gosModuleArchivistRuleGridStore'],
    pageSize: 100,
    model: 'GibsonOS.module.archivist.rule.model.Grid',
    constructor: function(data) {
        let me = this;

        me.proxy = {
            type: 'gosDataProxyAjax',
            url: baseDir + 'archivist/rule/index'
        };

        me.callParent(arguments);
    }
});