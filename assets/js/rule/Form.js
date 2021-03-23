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
        let save = false;

        me.down('#coreEventElementParameterSaveButton').on('click', () => {
            let parameters = {};

            me.items.each(field => {
                if (field.getName() === 'strategy') {
                    return true;
                }

                parameters[field.getName()] = field.getValue();
            });

            if (!save) {
                parameters = {parameters: Ext.encode(parameters)};
            }

            parameters.strategy = !me.getForm().findField('strategy')
                ? responseData.strategy
                : me.getForm().findField('strategy').getValue()
            ;
            parameters.configuration = !responseData.config ? '[]' : Ext.encode(responseData.config);

            GibsonOS.Ajax.request({
                url: baseDir + 'archivist/rule/' + (save ? 'save' : 'edit'),
                params: parameters,
                success(response) {
                    responseData = Ext.decode(response.responseText).data;

                    if (responseData.parameters) {
                        me.removeAll();
                        me.addFields(responseData.parameters);
                    }

                    save = !!responseData.files;

                    if (responseData.id) {
                        me.down('#coreEventElementParameterSaveButton').up().add({
                            text: 'Ausführen',
                            handler() {
                                GibsonOS.Ajax.request({
                                    url: baseDir + 'archivist/rule/execute',
                                    params: {
                                        id: responseData.id
                                    }
                                });
                            }
                        });
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