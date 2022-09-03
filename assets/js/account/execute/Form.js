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
            me.execute();
        });
    },
    execute() {
        const me = this;
        const saveButton = me.down('#coreEventElementParameterSaveButton');
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
            timeout: 120,
            xtype: 'gosFormActionAction',
            url: baseDir + 'archivist/account/execute',
            params: {
                id: me.accountId
            },
            success(form, action) {
                me.removeAll();
                const data = action.result.data;

                if (Object.keys(data).length) {
                    Ext.iterate(data, (name, parameter) => {
                        me.addField('parameters[' + name + ']', parameter);
                    });
                } else {
                    me.add({
                        name: 'status',
                        xtype: 'gosCoreComponentFormFieldDisplay',
                        hideLabel: true
                    });
                    saveButton.hide();
                    getStatus();
                }

                me.setLoading(false);
            }
        });
    }
});