Ext.define('GibsonOS.module.archivist.rule.Form', {
    extend: 'GibsonOS.module.core.parameter.Form',
    alias: ['widget.gosModuleArchivistRuleForm'],
    ruleId: null,
    initComponent() {
        let me = this;

        me.callParent();

        me.down('#coreEventElementParameterSaveButton').on('click', () => {
            me.setLoading(true);

            const form = me.getForm();

            form.submit({
                xtype: 'gosFormActionAction',
                url: baseDir + 'archivist/rule/save',
                params: {
                    id: me.ruleId,
                    accountId: me.accountId
                },
                failure() {
                    me.setLoading(false);
                },
                success() {
                    me.setLoading(false);
                }
            });
        });
    }
});