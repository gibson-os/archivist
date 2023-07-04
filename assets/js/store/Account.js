Ext.define('GibsonOS.module.archivist.store.Account', {
    extend: 'GibsonOS.data.Store',
    alias: ['store.gosModuleArchivistAccountStore'],
    pageSize: 100,
    model: 'GibsonOS.module.archivist.model.Account',
    constructor(data) {
        let me = this;

        me.proxy = {
            type: 'gosDataProxyAjax',
            url: baseDir + 'archivist/account',
            method: 'GET'
        };

        me.callParent(arguments);
    }
});