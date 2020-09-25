Ext.define('GibsonOS.module.archivist.rule.Window', {
    extend: 'GibsonOS.Window',
    alias: ['widget.gosModuleArchivistRuleWindow'],
    title: 'Regel',
    width: 500,
    autoHeight: true,
    requiredPermission: {
        module: 'archivist',
        task: 'rule'
    },
    initComponent: function() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistRuleForm',
            gos: me.gos
        }];

        me.callParent();

        me.down('gosModuleArchivistRuleForm').on('afterSaveForm', function(form, action) {
            me.close();
        });
    }
});