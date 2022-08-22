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
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistRuleForm',
            ruleId: me.ruleId
        }];

        me.callParent();
    }
});