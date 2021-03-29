Ext.define('GibsonOS.module.archivist.rule.model.Strategy', {
    extend: 'GibsonOS.data.Model',
    idProperty: 'className',
    fields: [{
        name: 'className',
        type: 'string'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'configStep',
        type: 'int'
    },{
        name: 'parameters',
        type: 'object'
    }]
});