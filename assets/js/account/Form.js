Ext.define('GibsonOS.module.archivist.account.Form', {
    extend: 'GibsonOS.module.core.parameter.Form',
    alias: ['widget.gosModuleArchivistAccountForm'],
    accountId: null,
    initComponent() {
        let me = this;

        me.items = [{
            xtype: 'gosCoreComponentFormFieldTextField',
            name: 'name',
            fieldLabel: 'Name',
            parameterObject: {}
        },{
            xtype: 'gosModuleCoreParameterTypeAutoComplete',
            name: 'strategy',
            fieldLabel: 'Strategy',
            valueField: 'className',
            parameterObject: {
                config: {
                    model: 'GibsonOS.module.archivist.model.Strategy',
                    parameters: {
                        accountId: me.accountId
                    },
                    autoCompleteClassname: 'GibsonOS\\Module\\Archivist\\AutoComplete\\StrategyAutoComplete'
                }
            },
            listeners: {
                change(combo, value) {
                    me.items.each(field => {
                        if (field.getName() === 'name' || field.getName() === 'strategy') {
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
                    if (field.getName() === 'strategy' || field.getName() === 'name') {
                        return true;
                    }

                    parameters[field.getName()] = field.getValue();
                });

                parameters.strategy = me.getForm().findField('strategy').getValue();
                parameters.name = me.getForm().findField('name').getValue();
                parameters.id = me.accountId;

                return parameters;
            };

            GibsonOS.Ajax.request({
                url: baseDir + 'archivist/account/save',
                timeout: 120000,
                params: getParameters(),
                success(response) {
                    responseData = Ext.decode(response.responseText).data;

                    if (responseData.id) {
                        me.accountId = responseData.id;
                    }

                    // me.down('#coreEventElementParameterSaveButton').up().add({
                    //     itemId: 'archivistAccountFormExecuteButton',
                    //     text: 'Ausführen',
                    //     handler() {
                    //         GibsonOS.Ajax.request({
                    //             url: baseDir + 'archivist/account/execute',
                    //             params: getParameters(),
                    //             success(response) {
                    //                 const messageBox = GibsonOS.MessageBox.show({
                    //                     type: GibsonOS.MessageBox.type.INFO,
                    //                     title: 'Status von ' + Ext.decode(response.responseText).data.name,
                    //                     msg: 'Starte...'
                    //                 });
                    //                 const messageField = messageBox.down('displayfield');
                    //
                    //                 const reloadFunction = () => {
                    //                     if (messageBox.isHidden()) {
                    //                         return;
                    //                     }
                    //
                    //                     GibsonOS.Ajax.request({
                    //                         url: baseDir + 'archivist/account/status',
                    //                         params: {
                    //                             id: responseData.id
                    //                         },
                    //                         success(response) {
                    //                             messageField.setValue(Ext.decode(response.responseText).data.message + '...');
                    //                         }
                    //                     });
                    //                     setTimeout(reloadFunction, 100);
                    //                 };
                    //                 reloadFunction();
                    //             }
                    //         });
                    //     }
                    // });
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