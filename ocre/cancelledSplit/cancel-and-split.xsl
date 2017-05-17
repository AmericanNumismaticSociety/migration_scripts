<?xml version="1.0" encoding="UTF-8"?>

<!-- Author: Ethan Gruber
    Date: May 17, 2017
    Function: This XSLT stylesheet reads an XML document with old and new recordIDs and differences in typeDesc in order to 
    split and replace records (designed to deprecate IDs in OCRE that have been replaced with IDs with different denominations -->

<!-- Record Format 
    
    <record id="ric.2.hdn.226c">
            <new id="ric.2.hdn.226c_denarius" titleAppend="(denarius)">
                <denomination href="http://nomisma.org/id/denarius">Denarius</denomination>
                <material href="http://nomisma.org/id/ar">Silver</material>
            </new>
            <new id="ric.2.hdn.226c_aureus" titleAppend="(aureus)">
                <denomination href="http://nomisma.org/id/aureus">Aureus</denomination>
                <material href="http://nomisma.org/id/av">Gold</material>
            </new>
        </record>
        
-->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:nuds="http://nomisma.org/nuds" xmlns:xlink="http://www.w3.org/1999/xlink" version="2.0">
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

    <xsl:variable name="records" as="node()*">
        <xsl:copy-of select="/records"/>
    </xsl:variable>

    <xsl:template match="/">
        <xsl:for-each select="//record">
            <xsl:variable name="filename" select="concat(@id, '.xml')"/>
            
            <!-- cancel and split IDs -->
            <xsl:result-document href="{concat('out/', $filename)}">
                <xsl:apply-templates select="document(concat('in/', $filename))//nuds:nuds" mode="cancel"/>
            </xsl:result-document>
            
            <!-- generate new ids -->
            <xsl:for-each select="new">
                <xsl:variable name="newFilename" select="concat(@id, '.xml')"/>
                <xsl:variable name="recordId" select="@id"/>
                
                <xsl:result-document href="{concat('out/', $newFilename)}">
                    <xsl:apply-templates select="document(concat('in/', $filename))//nuds:nuds" mode="split">
                        <xsl:with-param name="recordId" select="$recordId"/>
                    </xsl:apply-templates>
                </xsl:result-document>
            </xsl:for-each>
        </xsl:for-each>
    </xsl:template>
    
    <!-- templates to split NUDS record into new records -->
    <xsl:template match="@*|node()" mode="split">
        <xsl:param name="recordId"/>
        
        <xsl:copy>
            <xsl:apply-templates select="@*|node()" mode="split">
                <xsl:with-param name="recordId" select="$recordId"/>
            </xsl:apply-templates>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="nuds:recordId" mode="split">
        <xsl:param name="recordId"/>
        
        <xsl:element name="recordId" namespace="http://nomisma.org/nuds">
            <xsl:value-of select="$recordId"/>
        </xsl:element>
        
        <xsl:element name="otherRecordId" namespace="http://nomisma.org/nuds">
            <xsl:attribute name="semantic">dcterms:replaces</xsl:attribute>
            <xsl:value-of select="."/>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:maintenanceHistory" mode="split">
        <xsl:element name="maintenanceHistory" namespace="http://nomisma.org/nuds">
            <xsl:element name="maintenanceEvent" namespace="http://nomisma.org/nuds">
                <xsl:element name="eventType" namespace="http://nomisma.org/nuds">derived</xsl:element>
                <xsl:element name="eventDateTime" namespace="http://nomisma.org/nuds">
                    <xsl:attribute name="standardDateTime" select="current-dateTime()"/>
                    <xsl:value-of select="format-dateTime(current-dateTime(), '[D1] [MNn] [Y0001] [H01]:[m01]:[s01]')"/>
                </xsl:element>
                <xsl:element name="agentType" namespace="http://nomisma.org/nuds">machine</xsl:element>
                <xsl:element name="agent" namespace="http://nomisma.org/nuds">XSLT</xsl:element>
                <xsl:element name="eventDescription" namespace="http://nomisma.org/nuds">Derived new ID from cancelled and split record.</xsl:element>
            </xsl:element>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:title" mode="split">
        <xsl:param name="recordId"/>
        
        <xsl:element name="title" namespace="http://nomisma.org/nuds">
            <xsl:attribute name="xml:lang">en</xsl:attribute>
            <xsl:value-of select="concat(., ' ', $records//new[@id=$recordId]/@titleAppend)"/>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:typeDesc" mode="split">
        <xsl:param name="recordId"/>
        
        <xsl:element name="typeDesc" namespace="http://nomisma.org/nuds">
            <!-- apply all templates except for denomination and material -->
            <xsl:apply-templates select="node()[not(local-name() = 'denomination') and not(local-name()='material')]" mode="split"/>
            <xsl:for-each select="$records//new[@id=$recordId]/*">
                <xsl:element name="{name()}" namespace="http://nomisma.org/nuds">
                    <xsl:attribute name="xlink:type">simple</xsl:attribute>
                    <xsl:attribute name="xlink:href" select="@href"/>
                    <xsl:value-of select="."/>
                </xsl:element>
            </xsl:for-each>
        </xsl:element>
    </xsl:template>
    
    <!-- templates for NUDS record that is to be cancelled -->
    <xsl:template match="@*|node()" mode="cancel">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()" mode="cancel"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="nuds:recordId" mode="cancel">
        <xsl:variable name="recordId" select="."/>
        
        <xsl:element name="recordId" namespace="http://nomisma.org/nuds">
            <xsl:value-of select="."/>
        </xsl:element>
        
        <xsl:for-each select="$records/record[@id = $recordId]/new">
            <xsl:element name="otherRecordId" namespace="http://nomisma.org/nuds">
                <xsl:attribute name="semantic">dcterms:isReplacedBy</xsl:attribute>
                <xsl:value-of select="@id"/>
            </xsl:element>
        </xsl:for-each>
    </xsl:template>
    
    <xsl:template match="nuds:publicationStatus" mode="cancel">
        <xsl:element name="publicationStatus" namespace="http://nomisma.org/nuds">inProcess</xsl:element>
    </xsl:template>
    
    <xsl:template match="nuds:maintenanceStatus" mode="cancel">
        <xsl:element name="maintenanceStatus" namespace="http://nomisma.org/nuds">cancelledSplit</xsl:element>
    </xsl:template>

    <xsl:template match="nuds:maintenanceHistory" mode="cancel">
        <xsl:element name="maintenanceHistory" namespace="http://nomisma.org/nuds">
            <xsl:apply-templates mode="cancel"/>
            
            <xsl:element name="maintenanceEvent" namespace="http://nomisma.org/nuds">
                <xsl:element name="eventType" namespace="http://nomisma.org/nuds">cancelledSplit</xsl:element>
                <xsl:element name="eventDateTime" namespace="http://nomisma.org/nuds">
                    <xsl:attribute name="standardDateTime" select="current-dateTime()"/>
                    <xsl:value-of select="format-dateTime(current-dateTime(), '[D1] [MNn] [Y0001] [H01]:[m01]:[s01]')"/>
                </xsl:element>
                <xsl:element name="agentType" namespace="http://nomisma.org/nuds">machine</xsl:element>
                <xsl:element name="agent" namespace="http://nomisma.org/nuds">XSLT</xsl:element>
                <xsl:element name="eventDescription" namespace="http://nomisma.org/nuds">Cancelled and split with two IDs based on denomination.</xsl:element>
            </xsl:element>
        </xsl:element>
    </xsl:template>
    
    <!-- suppress denomination and material for the cancelled IDs -->
    <xsl:template match="nuds:denomination" mode="cancel"/>
    <xsl:template match="nuds:material" mode="cancel"/>
    
    
</xsl:stylesheet>
