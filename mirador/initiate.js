$(document).ready(function () {
    
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
        windowOptions[ "canvasId"] = canvas;
    }
    
    windowOptions[ "viewType"] = "ImageView";
    windowOptions[ "thumbnailNavigationPosition"] = "far-bottom";
    
    windowObjects.push(windowOptions);
    
    var miradorInstance = Mirador.viewer({
        "id": "viewer",
        "windows": windowObjects,
        "window": {
            "allowClose": true,
            "allowMaximize": true,
            "defaultSideBarPanel": 'info',
            "defaultView": 'gallery',
            "sideBarOpenByDefault": true,
            "forceDrawAnnotations": true
        },
        "thumbnailNavigation": {
            "defaultPosition": 'on'
        },
        "workspace": {
            "type": 'mosaic'
        },
        "workspaceControlPanel": {
            "enabled": true
        },
        "theme": {
            "palette": {
                "annotations": {
                    "hidden": {
                        "globalAlpha": 1
                    }
                }
            }
        }
    });
});


function getUrlVar(key) {
    var result = new RegExp(key + "=([^&]*)", "i").exec(window.location.search);
    return result && unescape(result[1]) || "";
}