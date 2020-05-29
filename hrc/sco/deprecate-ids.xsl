<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nuds="http://nomisma.org/nuds" xmlns:tei="http://www.tei-c.org/ns/1.0"
    exclude-result-prefixes="xs"
    version="2.0">
    
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>
    
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="nuds:maintenanceHistory">
        <xsl:element name="maintenanceHistory" namespace="http://nomisma.org/nuds">
            <xsl:apply-templates/>
            
            <maintenanceEvent xmlns="http://nomisma.org/nuds" xsl:exclude-result-prefixes="nuds">
                <eventType>derived</eventType>
                <eventDateTime standardDateTime="{current-dateTime()}"><xsl:value-of select="format-dateTime(current-dateTime(), '[Y0001]-[M01]-[D01]')"/></eventDateTime>
                <agentType>machine</agentType>
                <agent>XSLT</agent>
                <eventDescription>Deprecated sc.2. URIs and redirected back to URIs based on the printed Seleucid Coinage type corpus.</eventDescription>
            </maintenanceEvent>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:otherRecordId[@semantic='dcterms:replaces']">
        <xsl:element name="otherRecordId" namespace="http://nomisma.org/nuds">
            <xsl:attribute name="semantic">dcterms:isReplacedBy</xsl:attribute>
            <xsl:value-of select="."/>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:publicationStatus">
        <xsl:element name="publicationStatus" namespace="http://nomisma.org/nuds">
            <xsl:text>deprecatedType</xsl:text>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:maintenanceStatus">
        <xsl:element name="maintenanceStatus" namespace="http://nomisma.org/nuds">
            <xsl:text>cancelledReplaced</xsl:text>
        </xsl:element>
    </xsl:template>
</xsl:stylesheet>
