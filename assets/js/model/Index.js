Ext.define('GibsonOS.module.archivist.model.Index', {
    extend: 'GibsonOS.data.Model',
    fields: [{
        name: 'id',
        type: 'int'
    },{
        name: 'inputPath',
        type: 'string'
    },{
        name: 'outputPath',
        type: 'string'
    },{
        name: 'size',
        type: 'int'
    },{
        name: 'error',
        type: 'string'
    },{
        name: 'changed',
        type: 'string'
    }]
});