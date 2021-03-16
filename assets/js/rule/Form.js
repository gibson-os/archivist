Ext.define('GibsonOS.module.archivist.rule.Form', {
    extend: 'GibsonOS.module.core.parameter.Form',
    alias: ['widget.gosModuleArchivistRuleForm'],
    initComponent: function () {
        let me = this;

        me.items = [{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Name',
            name: 'name'
        },{
            xtype: 'gosModuleCoreParameterTypeAutoComplete',
            name: 'strategy',
            fieldLabel: 'Strategy',
            parameterObject: {
                config: {
                    model: 'GibsonOS.module.archivist.rule.model.Strategy',
                    parameters: {},
                    autoCompleteClassname: 'GibsonOS\\Module\\Archivist\\AutoComplete\\StrategyAutoComplete'
                }
            }
        }];

        me.callParent();
    }
});