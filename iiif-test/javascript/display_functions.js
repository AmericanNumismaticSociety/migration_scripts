/************************************
VISUALIZATION FUNCTIONS
Written by Ethan Gruber, gruber@numismatics.org
Library: jQuery
Description: Rendering graphics based on hoard counts
 ************************************/
$(document).ready(function () {
    var manifest = 'http://admin.numismatics.org/loris/1858%2F1858.1.2.obv.2300.tif/info.json';

    var iiifImage = L.map('iiif-container', {
        center:[0, 0],
        crs: L.CRS.Simple,
        zoom: 0
    });
    
    // Grab a IIIF manifest
    $.getJSON(manifest, function (data) {
        //determine where it is a collection or image manifest
        if (data[ '@context'] == 'http://iiif.io/api/image/2/context.json' || data[ '@context'] == 'http://library.stanford.edu/iiif/image-api/1.1/context.json') {
            L.tileLayer.iiif(manifest).addTo(iiifImage);
        } else if (data[ '@context'] == 'http://iiif.io/api/presentation/2/context.json') {
            var iiifLayers = {
            };
            
            // For each image create a L.TileLayer.Iiif object and add that to an object literal for the layer control
            $.each(data.sequences[0].canvases, function (_, val) {
                iiifLayers[val.label] = L.tileLayer.iiif(val.images[0].resource.service[ '@id'] + '/info.json');
            });
            // Add layers control to the map
            L.control.layers(iiifLayers).addTo(iiifImage);
            
            // Access the first Iiif object and add it to the map
            iiifLayers[Object.keys(iiifLayers)[0]].addTo(iiifImage);
        }
    });
});