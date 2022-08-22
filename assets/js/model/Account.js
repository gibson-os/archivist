Ext.define('GibsonOS.module.archivist.rule.model.Account', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'int'
    },{
        name: 'strategy',
        type: 'string'
    },{
        name: 'name',
        type: 'string'
    },{
        name: 'active',
        type: 'bool'
    }]
});