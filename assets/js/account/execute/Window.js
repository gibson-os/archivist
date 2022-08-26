Ext.define('GibsonOS.module.archivist.account.execute.Window', {
    extend: 'GibsonOS.Window',
    alias: ['widget.gosModuleArchivistAccountExecuteWindow'],
    title: 'Ausführen',
    width: 500,
    autoHeight: true,
    requiredPermission: {
        module: 'archivist',
        task: 'account'
    },
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistAccountExecuteForm',
            accountId: me.accountId
        }];

        me.callParent();

        const formPanel = me.down('gosModuleCoreParameterForm');
        const saveButton = formPanel.down('#coreEventElementParameterSaveButton');

        formPanel.on('render', () => {
            formPanel.execute();
        });
    }
});