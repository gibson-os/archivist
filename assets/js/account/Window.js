Ext.define('GibsonOS.module.archivist.account.Window', {
    extend: 'GibsonOS.module.core.component.form.Window',
    alias: ['widget.gosModuleArchivistAccountWindow'],
    title: 'Account',
    width: 500,
    autoHeight: true,
    requiredPermission: {
        module: 'archivist',
        task: 'account'
    },
    initComponent() {
        const me = this;

        me.url = baseDir + 'archivist/account/form';
        me.params = {
            accountId: me.accountId
        };

        me.callParent();

        const form = me.down('form');
        const basicForm = form.getForm();

        form.on('afterAddFields', () => {
            const strategyField = basicForm.findField('strategy');

            strategyField.on('change', (field, value) => {
                basicForm.getFields().each((formField) => {
                    if (formField.getName().startsWith('configuration[')) {
                        form.remove(formField);
                    }
                });

                Ext.iterate(field.findRecordByValue(value).get('parameters'), (name, parameter) => {
                    form.addField('configuration[' + name + ']', parameter);
                });
            });
        });
        basicForm.on('actioncomplete', () => {
            me.close();
        });
    }
});