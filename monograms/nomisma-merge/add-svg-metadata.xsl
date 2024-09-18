<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:cc="http://creativecommons.org/ns#" xmlns:xs="http://www.w3.org/2001/XMLSchema"
    xmlns:crmdig="http://www.ics.forth.gr/isl/CRMdig/" xmlns:nmo="http://nomisma.org/ontology#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:dcterms="http://purl.org/dc/terms/" xmlns:foaf="http://xmlns.com/foaf/0.1/" xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg"
    xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:crm="http://www.cidoc-crm.org/cidoc-crm/" xmlns:skos="http://www.w3.org/2004/02/skos/core#"
    exclude-result-prefixes="xs skos crmdig dcterms" version="2.0">

    <xsl:strip-space elements="*"/>

    <xsl:output encoding="UTF-8" indent="yes"/>

    <xsl:variable name="rdf" as="item()*">
        <xsl:copy-of
            select="document(concat('file:///usr/local/projects/migration_scripts/monograms/nomisma-merge/rdf/', replace(tokenize(base-uri(), '/')[last()], 'svg', 'rdf')))"/>
    </xsl:variable>

    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="svg:svg">
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:nmo="http://nomisma.org/ontology#" xmlns:svg="http://www.w3.org/2000/svg" xmlns:crm="http://www.cidoc-crm.org/cidoc-crm/"
            xmlns:foaf="http://xmlns.com/foaf/0.1/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#"
            xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd"
            xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape">
            <xsl:apply-templates select="@* | node()"/>
        </svg>
    </xsl:template>

    <xsl:template match="svg:title">
        <title property="dc:title">
            <xsl:value-of select="$rdf//skos:prefLabel[@xml:lang = 'en']"/>
        </title>
        <desc proprety="dc:description">
            <xsl:value-of select="$rdf//skos:definition[@xml:lang = 'en']"/>
        </desc>
    </xsl:template>

    <xsl:template match="svg:metadata">
        <metadata>
            <rdf:RDF>
                <cc:Work rdf:about="{$rdf//crmdig:D1_Digital_Object/@rdf:about}">
                    <dc:title>
                        <xsl:value-of select="$rdf//skos:prefLabel[@xml:lang = 'en']"/>
                    </dc:title>
                    <dc:description>
                        <xsl:value-of select="$rdf//skos:definition[@xml:lang = 'en']"/>
                    </dc:description>
                    <foaf:focus rdf:resource="{$rdf/rdf:RDF/nmo:Monogram/@rdf:about}"/>
                    <dc:format>image/svg+xml</dc:format>
                    <dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage"/>
                    <xsl:if test="$rdf/descendant::dcterms:creator">
                        <dc:creator rdf:resource="{$rdf/descendant::dcterms:creator/@rdf:resource}"/>
                    </xsl:if>
                    <cc:license rdf:resource="https://creativecommons.org/choose/mark/"/>
                    <xsl:for-each select="$rdf/rdf:RDF/nmo:Monogram/crm:P106_is_composed_of">
                        <crm:P106_is_composed_of>
                            <xsl:value-of select="."/>
                        </crm:P106_is_composed_of>
                    </xsl:for-each>
                </cc:Work>
            </rdf:RDF>
        </metadata>
    </xsl:template>



</xsl:stylesheet>
