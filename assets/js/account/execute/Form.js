Ext.define('GibsonOS.module.archivist.account.execute.Form', {
    extend: 'GibsonOS.module.core.parameter.Form',
    alias: ['widget.gosModuleArchivistAccountExecuteForm'],
    ruleId: null,
    initComponent() {
        let me = this;

        me.callParent();

        const saveButton = me.down('#coreEventElementParameterSaveButton');

        saveButton.setText('Ausführen');
        saveButton.on('click', () => {
            me.setLoading(true);

            const form = me.getForm();

            form.submit({
                xtype: 'gosFormActionAction',
                url: baseDir + 'archivist/account/execute',
                params: {
                    id: me.accountId
                },
                failure() {
                    me.setLoading(false);
                },
                success() {
                    me.setLoading(false);
                }
            });
        });
    },
    execute() {
        const me = this;
        // const saveButton = me.down('#coreEventElementParameterSaveButton');
        const form = me.getForm();

        me.setLoading(true);

        const getStatus = () => {
            GibsonOS.Ajax.request({
                url: baseDir + 'archivist/account/status',
                params: {
                    id: me.accountId
                },
                success(response) {
                    if (!me.isVisible()) {
                        return;
                    }

                    form.findField('status').setValue(Ext.decode(response.responseText).data.message + '...');
                    setTimeout(getStatus, 1000);
                }
            });
        };

        form.submit({
            xtype: 'gosFormActionAction',
            url: baseDir + 'archivist/account/execute',
            params: {
                id: me.accountId
            },
            success(form, action) {
                me.removeAll();
                const data = action.result.data;

                if (data.length) {
                    Ext.iterate(data.parameters, (name, parameter) => {
                        me.addField('parameter[' + name + ']', parameter);
                    });
                } else {
                    // saveButton.setText('Schließen');
                    me.add({
                        name: 'status',
                        xtype: 'gosCoreComponentFormFieldDisplay',
                        hideLabel: true
                    });
                    getStatus();
                }

                me.setLoading(false);
            }
        });
    },
});