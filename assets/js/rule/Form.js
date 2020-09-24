Ext.define('GibsonOS.module.archivist.rule.Form', {
    extend: 'GibsonOS.form.Panel',
    alias: ['widget.gosModuleArchivistRuleForm'],
    itemId: 'archivistRuleForm',
    defaults: {
        border: false,
        xtype: 'panel',
        flex: 1,
        layout: 'anchor'
    },
    border: false,
    initComponent: function () {
        let me = this;

        me.items = [{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Name',
            name: 'name'
        },{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Beobachtetes Verzeichnis',
            name: 'observeDirectory'
        },{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Beobachtete Dateinamen',
            name: 'observeFilename'
        },{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Ziel Verzeichnis',
            name: 'moveDirectory'
        },{
            xtype: 'gosFormTextfield',
            fieldLabel: 'Ziel Dateiname',
            name: 'moveFilename'
        },{
            xtype: 'gosFormCheckbox',
            name: 'active',
            boxLabel: 'Aktiv'
        },{
            xtype: 'gosFormNumberfield',
            fieldLabel: 'Anzahl',
            name: 'count'
        }];

        me.callParent();
    }
});