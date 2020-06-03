<?xml version="1.0" encoding="UTF-8"?>

<!-- Author: Ethan Gruber
    Date: June 2020
    Function: This deprecates the old Hadrian IDs, either cancelled, cancelledReplaced, or cancelledSplit -->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:nuds="http://nomisma.org/nuds" xmlns:xlink="http://www.w3.org/1999/xlink" version="2.0">
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>
    
    <xsl:variable name="concordance" as="node()*">
        <xsl:copy-of select="document('concordance.xml')"/>
    </xsl:variable>
    
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="nuds:control">
        <xsl:element name="control" namespace="http://nomisma.org/nuds">
            <xsl:apply-templates select="nuds:recordId"/>
            
            <xsl:apply-templates select="*[not(local-name() = 'recordId')]">
                <xsl:with-param name="recordId" select="nuds:recordId"/>
            </xsl:apply-templates>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:maintenanceStatus">
        <xsl:param name="recordId"/>
        
        <xsl:choose>
            <!-- if there are already otherRecordIds, then this is an older type that was subsequently split into denominations -->
            <xsl:when test="parent::node()/nuds:otherRecordId">
                <xsl:element name="maintenanceStatus" namespace="http://nomisma.org/nuds">
                    <xsl:value-of select="."/>
                </xsl:element>
            </xsl:when>
            
            <xsl:otherwise>
                <xsl:choose>
                    <xsl:when test="count($concordance//type[@old = $recordId]) &gt; 1">
                        <xsl:apply-templates select="$concordance//type[@old = $recordId]"/>                        
                        <xsl:element name="maintenanceStatus" namespace="http://nomisma.org/nuds">cancelledSplit</xsl:element>
                    </xsl:when>
                    <xsl:when test="count($concordance//type[@old = $recordId]) = 1">
                        <xsl:apply-templates select="$concordance//type[@old = $recordId]"/>
                        <xsl:element name="maintenanceStatus" namespace="http://nomisma.org/nuds">cancelledReplaced</xsl:element>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:element name="maintenanceStatus" namespace="http://nomisma.org/nuds">cancelled</xsl:element>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    
    <xsl:template match="nuds:maintenanceHistory">
        <xsl:element name="maintenanceHistory" namespace="http://nomisma.org/nuds">
            <xsl:apply-templates/>
            
            <xsl:element name="maintenanceEvent" namespace="http://nomisma.org/nuds">
                <xsl:element name="eventType" namespace="http://nomisma.org/nuds">derived</xsl:element>
                <xsl:element name="eventDateTime" namespace="http://nomisma.org/nuds">
                    <xsl:attribute name="standardDateTime" select="current-dateTime()"/>
                    <xsl:value-of select="format-dateTime(current-dateTime(), '[D1] [MNn] [Y0001] [H01]:[m01]:[s01]')"/>
                </xsl:element>
                <xsl:element name="agentType" namespace="http://nomisma.org/nuds">machine</xsl:element>
                <xsl:element name="agent" namespace="http://nomisma.org/nuds">XSLT</xsl:element>
                <xsl:element name="eventDescription" namespace="http://nomisma.org/nuds">Deprecated Hadrian record and replaced with ID from new volume.</xsl:element>
            </xsl:element>
        </xsl:element>
    </xsl:template>    
    
    <xsl:template match="nuds:publicationStatus">
        <xsl:element name="publicationStatus" namespace="http://nomisma.org/nuds">deprecatedType</xsl:element>
    </xsl:template>
    
    <xsl:template match="type">
        <xsl:element name="otherRecordId" namespace="http://nomisma.org/nuds">
            <xsl:attribute name="semantic">dcterms:isReplacedBy</xsl:attribute>
            <xsl:value-of select="@new"/>
        </xsl:element>
        <xsl:element name="otherRecordId" namespace="http://nomisma.org/nuds">
            <xsl:attribute name="semantic">skos:exactMatch</xsl:attribute>
            <xsl:value-of select="concat('http://numismatics.org/ocre/id/', @new)"/>
        </xsl:element>
    </xsl:template>
   
  
</xsl:stylesheet>
