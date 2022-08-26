Ext.define('GibsonOS.module.archivist.model.Account', {
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
    }]
});