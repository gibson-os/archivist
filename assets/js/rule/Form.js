Ext.define('GibsonOS.module.archivist.rule.Form', {
    extend: 'GibsonOS.module.core.parameter.Form',
    alias: ['widget.gosModuleArchivistRuleForm'],
    ruleId: null,
    initComponent: function () {
        let me = this;

        me.items = [{
            xtype: 'gosModuleCoreParameterTypeAutoComplete',
            name: 'strategy',
            fieldLabel: 'Strategy',
            valueField: 'className',
            parameterObject: {
                config: {
                    model: 'GibsonOS.module.archivist.rule.model.Strategy',
                    parameters: {
                        ruleId: me.ruleId
                    },
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
            me.setLoading(true);

            const getParameters = () => {
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
                    ? (responseData.className ?? responseData.strategy)
                    : me.getForm().findField('strategy').getValue()
                ;
                parameters.configuration = !responseData.config ? '[]' : Ext.encode(responseData.config);
                parameters.id = me.ruleId;
                parameters.configStep = responseData.configStep ?? 0;

                return parameters;
            };

            GibsonOS.Ajax.request({
                url: baseDir + 'archivist/rule/' + (save ? 'save' : 'edit'),
                timeout: 120000,
                params: getParameters(),
                success(response) {
                    responseData = Ext.decode(response.responseText).data;

                    if (responseData.parameters) {
                        me.removeAll();
                        me.addFields(responseData.parameters);
                    }

                    save = save || !!responseData.lastStep;

                    if (
                        responseData.id &&
                        !me.down('#archivistRuleFormExecuteButton')
                    ) {
                        me.down('#coreEventElementParameterSaveButton').up().add({
                            itemId: 'archivistRuleFormExecuteButton',
                            text: 'Ausführen',
                            handler() {
                                GibsonOS.Ajax.request({
                                    url: baseDir + 'archivist/rule/execute',
                                    params: getParameters(),
                                    success(response) {
                                        const messageBox = GibsonOS.MessageBox.show({
                                            type: GibsonOS.MessageBox.type.INFO,
                                            title: 'Status von ' + Ext.decode(response.responseText).data.name,
                                            msg: 'Starte...'
                                        });
                                        const messageField = messageBox.down('displayfield');

                                        const reloadFunction = () => {
                                            if (messageBox.isHidden()) {
                                                return;
                                            }

                                            GibsonOS.Ajax.request({
                                                url: baseDir + 'archivist/rule/status',
                                                params: {
                                                    id: responseData.id
                                                },
                                                success(response) {
                                                    messageField.setValue(Ext.decode(response.responseText).data.message + '...');
                                                }
                                            });
                                            setTimeout(reloadFunction, 100);
                                        };
                                        reloadFunction();
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