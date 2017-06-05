#!/usr/bin/fontforge
Open($1); 
SelectWorthOutputting(); 
foreach Export("svg"); 
endloop;
