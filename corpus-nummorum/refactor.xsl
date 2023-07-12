<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema"
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:nmo="http://nomisma.org/ontology#"
    xmlns:skos="http://www.w3.org/2004/02/skos/core#" xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:foaf="http://xmlns.com/foaf/0.1/" xmlns:edm="http://www.europeana.eu/schemas/edm/"
    xmlns:svcs="http://rdfs.org/sioc/services#" xmlns:doap="http://usefulinc.com/ns/doap#"
    xmlns:crm="http://www.cidoc-crm.org/cidoc-crm/"
    xmlns:d2r="http://sites.wiwiss.fu-berlin.de/suhl/bizer/d2r-server/config.rdf#"
    xmlns:db="https://data.corpus-nummorum.eu/" xmlns:dcmitype="http://purl.org/dc/dcmitype/"
    xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"
    xmlns:meta="http://www4.wiwiss.fu-berlin.de/bizer/d2r-server/metadata#"
    xmlns:nm="http://nomisma.org/id/" xmlns:org="http://www.w3.org/ns/org#"
    xmlns:owl="http://www.w3.org/2002/07/owl#" xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
    xmlns:un="http://www.w3.org/2005/Incubator/urw3/XGR-urw3-20080331/Uncertainty.owl"
    xmlns:void="http://rdfs.org/ns/void#" xmlns:xsd="http://www.w3.org/2001/XMLSchema#"
    exclude-result-prefixes="xs" version="2.0">

    <xsl:strip-space elements="*"/>

    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="rdf:RDF">
        <rdf:RDF>
            <xsl:apply-templates/>
        </rdf:RDF>
    </xsl:template>

    <xsl:template
        match="rdf:Description[rdf:type[@rdf:resource = 'http://nomisma.org/ontology#TypeSeriesItem']]">
        <nmo:TypeSeriesItem rdf:about="{@rdf:about}">
            <xsl:apply-templates/>
        </nmo:TypeSeriesItem>
    </xsl:template>

    <xsl:template
        match="rdf:Description[rdf:type[@rdf:resource = 'http://nomisma.org/ontology#NumismaticObject']]">
        <nmo:NumismaticObject rdf:about="{@rdf:about}">
            <xsl:apply-templates/>
        </nmo:NumismaticObject>
    </xsl:template>

    <xsl:template match="skos:prefLabel">
        <skos:prefLabel xml:lang="en">
            <xsl:value-of select="."/>
        </skos:prefLabel>
    </xsl:template>

    <xsl:template match="dcterms:title">
        <dcterms:title xml:lang="en">
            <xsl:value-of select="."/>
        </dcterms:title>
    </xsl:template>

    <xsl:template
        match="rdf:Description[foaf:depiction[contains(@rdf:resource, 'gallica.bnf.fr') or contains(@rdf:resource, 'numismatics.org')]]">
        <xsl:variable name="service">
            <xsl:choose>
                <xsl:when test="foaf:depiction[contains(@rdf:resource, 'gallica.bnf.fr')]">
                    <xsl:value-of
                        select="replace(substring-before(foaf:depiction[contains(@rdf:resource, 'gallica.bnf.fr')]/@rdf:resource, '.highres'), 'ark:', 'iiif/ark:')"
                    />
                </xsl:when>
                <xsl:when test="foaf:depiction[contains(@rdf:resource, 'numismatics.org')]">
                    <xsl:value-of
                        select="replace(foaf:depiction[contains(@rdf:resource, 'numismatics.org')]/@rdf:resource, 'numismatics.org', 'images.numismatics.org')"
                    />
                </xsl:when>
            </xsl:choose>
        </xsl:variable>

        <rdf:Description rdf:about="{@rdf:about}">
            <foaf:thumbnail rdf:resource="{$service}/full/,120/0/default.jpg"/>
            <foaf:depiction rdf:resource="{$service}/full/600,/0/default.jpg"/>
        </rdf:Description>

        <edm:WebResource rdf:about="{$service}/full/600,/0/default.jpg">
            <svcs:has_service rdf:resource="{$service}"/>
            <dcterms:isReferencedBy rdf:resource="{$service}/info.json"/>
        </edm:WebResource>
        <svcs:Service rdf:about="{$service}">
            <dcterms:conformsTo rdf:resource="http://iiif.io/api/image"/>
            <doap:implements rdf:resource="http://iiif.io/api/image/2/level1.json"/>
        </svcs:Service>
    </xsl:template>

    <xsl:template match="rdf:type"/>

</xsl:stylesheet>
