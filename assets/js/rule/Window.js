Ext.define('GibsonOS.module.archivist.rule.Window', {
    extend: 'GibsonOS.Window',
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
        let me = this;

        me.items = [{
            xtype: 'gosModuleArchivistRuleForm',
            accountId: me.accountId,
            ruleId: me.ruleId
        }];

        if (me.ruleId !== null) {
            me.items = [{
                xtype: 'gosTabPanel',
                items: [{
                    xtype: 'gosModuleArchivistRuleForm',
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

        const formPanel = me.down('gosModuleCoreParameterForm');
        const form = formPanel.getForm();

        form.on('actioncomplete', () => {
            me.close();
        })
        formPanel.on('render', () => {
            formPanel.setLoading(true);

            GibsonOS.Ajax.request({
                url: baseDir + 'archivist/rule/edit',
                method: 'GET',
                params: {
                    id: me.ruleId,
                    accountId: me.accountId
                },
                success(response) {
                    formPanel.addFields(Ext.decode(response.responseText).data);
                    formPanel.setLoading(false);
                }
            });
        });
    }
});