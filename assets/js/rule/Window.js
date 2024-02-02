Ext.define('GibsonOS.module.archivist.rule.Window', {
    extend: 'GibsonOS.module.core.component.form.Window',
    alias: ['widget.gosModuleArchivistRuleWindow'],
    title: 'Regel',
    width: 500,
    autoHeight: true,
    accountId: null,
    ruleId: null,
    requiredPermission: {
        module: 'archivist',
        task: 'rule'
    },
    initComponent() {
        const me = this;

        me.url = baseDir + 'archivist/rule/edit'
        me.params = {
            id: me.ruleId,
            accountId: me.accountId
        };

        if (me.ruleId !== null) {
            me.items = [{
                xtype: 'gosTabPanel',
                items: [{
                    xtype: 'gosCoreComponentFormPanel',
                    title: 'Regel',
                    accountId: me.accountId,
                    ruleId: me.ruleId
                },{
                    xtype: 'gosModuleArchivistIndexGrid',
                    title: 'Indexierte Dateien',
                    ruleId: me.ruleId
                }]
            }];
        }

        me.callParent();

        const form = me.down('form');
        const basicForm = form.getForm();

        basicForm.on('actioncomplete', () => {
            me.close();
        });
    }
});