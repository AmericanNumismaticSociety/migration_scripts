<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nmo="http://nomisma.org/ontology#" xmlns:void="http://rdfs.org/ns/void#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:skos="http://www.w3.org/2004/02/skos/core#" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:foaf="http://xmlns.com/foaf/0.1/"
    xmlns:nm="http://nomisma.org/id/"
    exclude-result-prefixes="xs"
    version="2.0">
    
    <xsl:output method="text" encoding="UTF-8"/>
    <xsl:strip-space elements="*"/>
    
    <xsl:template match="/">
        <xsl:variable name="csv" as="node()*">
            <csv>
                <row>
                    <col>ID</col>
                    <col>Type</col>
                    <col>Label</col>
                    <col>Definition</col>
                    <col>Source</col>
                    <col>Image1</col>
                    <col>Image2</col>
                    <col>Image3</col>
                    <col>Creation Date</col>
                </row>
                <xsl:for-each select="collection('file:///home/komet/ans_migration/monograms/ocre?select=*.rdf')">
                    <xsl:apply-templates select="document(document-uri(.))/rdf:RDF"/>
                </xsl:for-each>
            </csv>
        </xsl:variable>
        
        <xsl:apply-templates select="$csv//row">
            <xsl:sort select="col[1]" order="ascending"/>
        </xsl:apply-templates>
    </xsl:template>
    
    <!-- process CSV metamodel to CSV text -->
    <xsl:template match="row">
        <xsl:for-each select="col">
            <xsl:text>"</xsl:text>
            <xsl:value-of select="."/>
            <xsl:text>"</xsl:text>
            
            <xsl:if test="not(position() = last())">
                <xsl:text>,</xsl:text>
            </xsl:if>
        </xsl:for-each>
        <xsl:text>&#x0A;</xsl:text>
    </xsl:template>
    
    <!-- process RDF to CSV metamodel -->
    <xsl:template match="rdf:RDF">
        <row>
            <xsl:apply-templates select="descendant::nmo:Monogram|descendant::nmo:Mintmark"/>
        </row>        
    </xsl:template>
    
    <xsl:template match="nmo:Monogram|nmo:Mintmark">  
        <col>
            <xsl:value-of select="tokenize(@rdf:about, '/')[last()]"/>
        </col>
        <col>
            <xsl:value-of select="name()"/>
        </col>
        <xsl:apply-templates select="skos:prefLabel"/>
        <xsl:apply-templates select="skos:definition"/>
        <xsl:apply-templates select="dcterms:source"/>
        
        <xsl:choose>
            <xsl:when test="count(foaf:depiction) = 1">
                <xsl:apply-templates select="foaf:depiction"/>
                <col/>
                <col/>
            </xsl:when>
            <xsl:when test="count(foaf:depiction) = 2">
                <xsl:apply-templates select="foaf:depiction"/>
                <col/>
            </xsl:when>
            <xsl:when test="count(foaf:depiction) = 3">
                <xsl:apply-templates select="foaf:depiction"/>
            </xsl:when>
        </xsl:choose>
        
        
        <xsl:apply-templates select="dcterms:created"/>       
    </xsl:template>
    
    <xsl:template match="skos:prefLabel|skos:definition|dcterms:source|dcterms:created">
        <col>
            <xsl:value-of select="if (@rdf:resource) then @rdf:resource else ."/>
        </col>
    </xsl:template>
    
    <xsl:template match="foaf:depiction">
        <col>
            <xsl:value-of select="concat('http://numismatics.org/symbolimages/ocre/', tokenize(@rdf:resource, '/')[last()], '.svg')"/>
        </col>
    </xsl:template>
    
</xsl:stylesheet>