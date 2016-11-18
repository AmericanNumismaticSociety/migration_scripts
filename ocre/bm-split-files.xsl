<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsd="http://www.w3.org/2001/XMLSchema#"
    xmlns:nm="http://nomisma.org/id/" xmlns:void="http://rdfs.org/ns/void#" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:foaf="http://xmlns.com/foaf/0.1/"
    xmlns:nmo="http://nomisma.org/ontology#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" exclude-result-prefixes="xs" version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output encoding="UTF-8" indent="yes"/>

    <xsl:variable name="files" select="('ric.1(2).', 'ric.2_1(2).', 'ric.2.', 'ric.3.', 'ric.4.', 'ric.5.', 'ric.6.', 'ric.7.', 'ric.8.', 'ric.9.', 'ric.10.', 'pella', 'crro')"/>

    <xsl:variable name="rdf" as="element()*">
        <xsl:copy-of select="/*"/>
    </xsl:variable>

    <xsl:template match="/">
        <xsl:for-each select="$files">
            <xsl:variable name="id" select="if (substring(., string-length(.), 1) = '.') then substring(., 1, (string-length(.) - 1)) else ." as="xs:string"/>
            
            <xsl:result-document href="{$id}.rdf">
                <rdf:RDF xmlns:xsd="http://www.w3.org/2001/XMLSchema#" xmlns:nm="http://nomisma.org/id/" xmlns:void="http://rdfs.org/ns/void#"
                    xmlns:dcterms="http://purl.org/dc/terms/" xmlns:foaf="http://xmlns.com/foaf/0.1/" xmlns:nmo="http://nomisma.org/ontology#"
                    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
                    <xsl:copy-of select="$rdf/descendant::nmo:NumismaticObject[contains(nmo:hasTypeSeriesItem/@rdf:resource, $id)]"/>
                </rdf:RDF>
            </xsl:result-document>
        </xsl:for-each>
    </xsl:template>

</xsl:stylesheet>
