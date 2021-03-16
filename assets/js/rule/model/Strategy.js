Ext.define('GibsonOS.module.archivist.rule.model.Strategy', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'int'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'parameters',
        type: 'object'
    }]
});