<?xml version="1.0" encoding="UTF-8"?>
<!-- Author: Ethan Gruber
    Date: November 2018
    Function: Deprecate the Nomisma IDs for Svoronos 1904 types and redirect to the relevant ID in the Ptolemaic Coinage Online (http://numismatics.org/pco) namespace -->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:prov="http://www.w3.org/ns/prov#"   xmlns:nmo="http://nomisma.org/ontology#"
    xmlns:dcterms="http://purl.org/dc/terms/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" exclude-result-prefixes="xs xsl" version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="nmo:TypeSeriesItem">
        <xsl:variable name="id" select="tokenize(tokenize(@rdf:about, '/')[last()], '-')[last()]"/>
        
        <xsl:element name="nmo:TypeSeriesItem" namespace="http://nomisma.org/ontology#">
            <xsl:apply-templates/>
            
            <dcterms:isReplacedBy rdf:resource="http://numismatics.org/pco/id/svoronos-1904.{$id}"/>
        </xsl:element>
    </xsl:template>
    
    <xsl:template match="dcterms:ProvenanceStatement">
        <xsl:element name="dcterms:ProvenanceStatement" namespace="http://purl.org/dc/terms/">
            <xsl:apply-templates/>
            <prov:wasInvalidatedBy>
                <prov:Activity>
                    <rdf:type rdf:resource="http://www.w3.org/ns/prov#Replace"/>
                    <prov:atTime rdf:datatype="http://www.w3.org/2001/XMLSchema#dateTime">
                        <xsl:value-of select="current-dateTime()"/>
                    </prov:atTime>
                    <prov:wasAssociatedWith rdf:resource="http://nomisma.org/editor/egruber"/>
                    <dcterms:type>manual</dcterms:type>
                    <dcterms:description xml:lang="en">Deprecated Svoronos IDs and linked to URI in the PCO namespace.</dcterms:description>
                </prov:Activity>
            </prov:wasInvalidatedBy>
        </xsl:element>
    </xsl:template>

</xsl:stylesheet>
