<?xml version="1.0" encoding="UTF-8"?>
<!-- Author: Ethan Gruber
    Date: February 2025
    Function: Insert links to CHANGE URIs into IGCH records 
-->
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nh="http://nomisma.org/nudsHoard"
    xmlns="http://nomisma.org/nudsHoard" xmlns:nuds="http://nomisma.org/nuds" xmlns:xlink="http://www.w3.org/1999/xlink" exclude-result-prefixes="xs nh" version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output encoding="UTF-8" indent="yes" method="xml"/>

    <xsl:variable name="recordId" select="descendant::nh:recordId"/>

    <xsl:variable name="concordance">
        <xsl:value-of select="document('concordance.xml')//hoard[igch[. = $recordId]]/@change"/>
    </xsl:variable>

    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="nh:control">
        <control>
            <xsl:apply-templates/>
            
            <semanticDeclaration>
                <prefix>skos</prefix>
                <namespace>http://www.w3.org/2004/02/skos/core#</namespace>
            </semanticDeclaration>
            <semanticDeclaration>
                <prefix>dcterms</prefix>
                <namespace>http://purl.org/dc/terms/</namespace>
            </semanticDeclaration>
        </control>
    </xsl:template>
    
    <!-- insert new maintenanceEvent -->
    <xsl:template match="nh:maintenanceHistory">
        <maintenanceHistory>
            <xsl:apply-templates/>
            
            <xsl:if test="string($concordance)">
                <maintenanceEvent>
                    <eventType>derived</eventType>
                    <eventDateTime standardDateTime="{current-dateTime()}">
                        <xsl:value-of select="format-dateTime(current-dateTime(), '[D] [MNn] [Y], [H01]:[m01]')"/>
                    </eventDateTime>
                    <agentType>human</agentType>
                    <agent>Ethan Gruber</agent>
                    <eventDescription>Inserted reference to CHANGE hoard URI.</eventDescription>
                </maintenanceEvent>
            </xsl:if>
            
        </maintenanceHistory>
    </xsl:template>

    <xsl:template match="nh:recordId">
        <recordId>
            <xsl:value-of select="."/>
        </recordId>

        <xsl:if test="string($concordance)">
            <otherRecordId semantic="skos:exactMatch">
                <xsl:value-of select="concat('http://coinhoards.org/id/', $concordance)"/>
            </otherRecordId>
        </xsl:if>

    </xsl:template>

    <xsl:template match="nh:descMeta">
        <descMeta>
            <xsl:apply-templates/>
            
            <!-- add refDesc if there is not one -->
            <xsl:if test="not(nh:refDesc) and string($concordance)">
                <refDesc>
                    <reference xlink:type="simple" xlink:href="{concat('http://coinhoards.org/id/', $concordance)}">
                        <xsl:value-of select="replace($concordance, 'change.', 'CHANGE ')"/>
                    </reference>
                </refDesc>
            </xsl:if>
        </descMeta>
    </xsl:template>
    
    <xsl:template match="nh:refDesc">
        <refDesc>
            <xsl:apply-templates/>
            
            <xsl:if test="string($concordance)">
                <reference xlink:type="simple" xlink:href="{concat('http://coinhoards.org/id/', $concordance)}">
                    <xsl:text>CHANGE </xsl:text>
                    <xsl:value-of select="number(replace($concordance, 'change.', ''))"/>
                </reference>
            </xsl:if>
        </refDesc>
    </xsl:template>

</xsl:stylesheet>
