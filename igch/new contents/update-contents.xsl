<?xml version="1.0" encoding="UTF-8"?>
<!-- Author: Ethan Gruber
    Date: February 2025
    Function: Embed new contents with coin type URIs into IGCH records
-->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nh="http://nomisma.org/nudsHoard" xmlns="http://nomisma.org/nudsHoard"  xmlns:nuds="http://nomisma.org/nuds"
    exclude-result-prefixes="xs nh"
    version="2.0">
    
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>
    
    <xsl:variable name="recordId" select="descendant::nh:recordId"/>
    
    <xsl:variable name="contents" as="item()">
        <xsl:choose>
            <xsl:when test="doc-available(concat('file:///usr/local/projects/migration_scripts/igch/new%20contents/contents/', $recordId, '.xml'))">
                <xsl:copy-of select="document(concat('file:///usr/local/projects/migration_scripts/igch/new%20contents/contents/', $recordId, '.xml'))/*"/>
            </xsl:when>
            <xsl:otherwise>
                <nh:contentsDesc/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:variable>
    
    
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>
    
    <!-- insert new maintenanceEvent -->
    <xsl:template match="nh:maintenanceHistory">
        <maintenanceHistory>
            <xsl:apply-templates/>
            
            <xsl:if test="$contents//nh:contents">
                <maintenanceEvent>
                    <eventType>derived</eventType>
                    <eventDateTime standardDateTime="{current-dateTime()}">
                        <xsl:value-of select="format-dateTime(current-dateTime(), '[D] [MNn] [Y], [H01]:[m01]')"/>
                    </eventDateTime>
                    <agentType>human</agentType>
                    <agent>Finn Conway</agent>
                    <eventDescription>Record revised to include coin type URIs in contents.</eventDescription>
                </maintenanceEvent>
            </xsl:if>
            
        </maintenanceHistory>
    </xsl:template>
    
    <xsl:template match="nh:contents">
        
        <contents>
           <xsl:apply-templates select="@*"/>
           <xsl:apply-templates select="nh:description"/>
            
            <xsl:choose>
                <xsl:when test="$contents//nh:contents">
                    <xsl:copy-of select="$contents//nh:coin|$contents//nh:coinGrp"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:apply-templates select="nh:coin|nh:coinGrp"/>
                </xsl:otherwise>
            </xsl:choose>
        </contents>
        
    </xsl:template>
    
</xsl:stylesheet>