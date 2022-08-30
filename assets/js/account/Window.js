Ext.define('GibsonOS.module.archivist.account.Window', {
    extend: 'GibsonOS.Window',
    alias: ['widget.gosModuleArchivistAccountWindow'],
    title: 'Account',
    width: 500,
    autoHeight: true,
    requiredPermission: {
        module: 'archivist',
        task: 'account'
    },
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistAccountForm',
            accountId: me.accountId
        }];

        me.callParent();

        const formPanel = me.down('gosModuleCoreParameterForm');
        const form = formPanel.getForm();

        form.on('actioncomplete', () => {
            me.close();
        })
    }
});