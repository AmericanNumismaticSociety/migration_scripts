/************************************
VISUALIZATION FUNCTIONS
Written by Ethan Gruber, gruber@numismatics.org
Library: jQuery
Description: Rendering graphics based on hoard counts
 ************************************/
$(document).ready(function () {
    myMiradorInstance = Mirador({
        "id": "viewer",
        "layout": "1x1",
        "buildPath": "http://projectmirador.org/demo/",
        "data":[ {
            "manifestUri": "http://app1.numismatics.org/search/manifest/1995.11.1648", "location": "American Numismatic Society"
        }],
        "mainMenuSettings": {
            "show": false
        },
        "windowObjects":[ {
            loadedManifest: "http://app1.numismatics.org/search/manifest/1995.11.1648",
            viewType: "ImageView"
        }],
        "windowSettings": {
            "canvasControls": {
                // The types of controls available to be displayed on a canvas
                "imageManipulation": {
                    "manipulationLayer": true,
                    "controls": {
                        "mirror": true
                    }
                }
            }
        }
    });
});