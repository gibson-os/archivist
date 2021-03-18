Ext.define('GibsonOS.module.archivist.rule.model.Strategy', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'string'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'parameters',
        type: 'object'
    }]
});