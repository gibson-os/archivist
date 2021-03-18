Ext.define('GibsonOS.module.archivist.rule.Form', {
    extend: 'GibsonOS.module.core.parameter.Form',
    alias: ['widget.gosModuleArchivistRuleForm'],
    initComponent: function () {
        let me = this;

        me.items = [{
            xtype: 'gosModuleCoreParameterTypeAutoComplete',
            name: 'strategy',
            fieldLabel: 'Strategy',
            parameterObject: {
                config: {
                    model: 'GibsonOS.module.archivist.rule.model.Strategy',
                    parameters: {},
                    autoCompleteClassname: 'GibsonOS\\Module\\Archivist\\AutoComplete\\StrategyAutoComplete'
                }
            },
            listeners: {
                change(combo, value) {
                    me.items.each(field => {
                        if (field.getName() === 'strategy') {
                            return true;
                        }

                        me.remove(field);
                    });

                    const strategy = combo.getStore().getById(value);
                    me.addFields(strategy.get('parameters'));
                }
            }
        }];

        me.callParent();

        let responseData = {};

        me.down('#coreEventElementParameterSaveButton').on('click', () => {
            let parameters = {};

            me.items.each(field => {
                if (field.getName() === 'strategy') {
                    return true;
                }

                parameters[field.getName()] = field.getValue();
            });

            GibsonOS.Ajax.request({
                url: baseDir + 'archivist/rule/save',
                params: {
                    strategy: !me.getForm().findField('strategy')
                        ? responseData.id
                        : me.getForm().findField('strategy').getValue(),
                    configuration: !responseData.config ? '[]' : Ext.encode(responseData.config),
                    parameters: Ext.encode(parameters)
                },
                success(response) {
                    responseData = Ext.decode(response.responseText).data;

                    if (responseData.parameters) {
                        me.removeAll();
                        me.addFields(responseData.parameters);
                    }
                },
                callback() {
                    me.setLoading(false);
                }
            });
        }, this, {
            priority: -999
        });
    }
});