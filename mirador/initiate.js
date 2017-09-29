$(document).ready(function () {
    // Called without "let" or "var"
    // so we can play with it in the browser
    
    var manifest = getUrlVar('manifest');
    var publisher = getUrlVar('publisher');
    var canvas = getUrlVar('canvas');
    
    var windowObjects =[];
    var windowOptions = {
    };
    
    if (manifest) {
        windowOptions[ "loadedManifest"] = manifest;
    }
    if (canvas) {
        windowOptions[ "canvasID"] = canvas;
    }
    
    windowOptions[ "viewType"] = "ImageView";
    windowOptions[ "annotationLayer"] = true;
    windowOptions[ "annotationCreation"] = false;
    windowOptions[ "annotationState"] = "annoOnCreateOff";
    windowOptions[ "displayLayout"] = false;
    
    
    windowObjects.push(windowOptions);
    
    Mirador({
        "id": "viewer",
        "layout": "1x1",
        "data":[ {
            "manifestUri": manifest, "location": publisher
        }],
        "windowObjects": windowObjects,
        "sidePanelVisible": false
    });
});


function getUrlVar(key) {
    var result = new RegExp(key + "=([^&]*)", "i").exec(window.location.search);
    return result && unescape(result[1]) || "";
}