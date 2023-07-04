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

                    Ext.iterate(strategy.get('parameters'), (name, parameter) => {
                        me.addField('configuration[' + name + ']', parameter);
                    });
                }
            }
        }];

        me.callParent();

        me.down('#coreEventElementParameterSaveButton').on('click', () => {
            me.setLoading(true);

            me.getForm().submit({
                url: baseDir + 'archivist/account',
                method: 'POST',
                timeout: 120000,
                params: {
                    id: me.accountId
                },
                success() {
                    me.setLoading(false);
                },
                failure() {
                    me.setLoading(false);
                }
            });
        }, this, {
            priority: -999
        });
    }
});