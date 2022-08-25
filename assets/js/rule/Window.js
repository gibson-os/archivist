Ext.define('GibsonOS.module.archivist.rule.Window', {
    extend: 'GibsonOS.Window',
    alias: ['widget.gosModuleArchivistRuleWindow'],
    title: 'Regel',
    width: 500,
    autoHeight: true,
    ruleId: null,
    requiredPermission: {
        module: 'archivist',
        task: 'rule'
    },
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistRuleForm',
            accountId: me.accountId,
            ruleId: me.ruleId
        }];

        me.callParent();

        me.down('form').getForm().on('actioncomplete', () => {
            me.close();
        })
    }
});