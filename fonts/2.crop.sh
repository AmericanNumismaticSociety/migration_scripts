#!/bin/sh
for i in `ls *.svg`; do
inkscape --verb=FitCanvasToDrawing --verb=FileSave --verb=FileClose $i --verb=FileQuit;
done
