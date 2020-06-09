<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nmo="http://nomisma.org/ontology#"
    xmlns:void="http://rdfs.org/ns/void#" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:skos="http://www.w3.org/2004/02/skos/core#"
    xmlns:dcterms="http://purl.org/dc/terms/" xmlns:foaf="http://xmlns.com/foaf/0.1/" xmlns:nm="http://nomisma.org/id/" xmlns:nomisma="http://nomisma.org/"
    exclude-result-prefixes="xs nomisma" version="2.0">

    <xsl:output method="text" encoding="UTF-8"/>
    <xsl:strip-space elements="*"/>

    <xsl:template match="/">
        <xsl:variable name="csv" as="node()*">
            <csv>
                <row>
                    <col>ID</col>
                    <col>Constituent Letters</col>
                    <col>Label</col>
                    <col>Definition</col>
                    <col>Source</col>
                    <col>Image1</col>
                    <col>Contributor</col>
                    <col>Image Creator</col>
                </row>
                <xsl:apply-templates select="//folder[@name = 'Price']/file"/>
            </csv>
        </xsl:variable>

        <xsl:apply-templates select="$csv//row">
            <!--<xsl:sort select="col[1]" order="ascending"/>-->
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
    <xsl:template match="file">
        <xsl:variable name="num" select="tokenize(@name, '\.')[3]"/>

        <row>
            <col>
                <xsl:value-of select="substring-before(@name, '.svg')"/>
            </col>
            <col>
                <xsl:value-of select="normalize-space(@letters)"/>
            </col>
            <col>Price Monogram <xsl:value-of select="$num"/></col>
            <col>
                <xsl:value-of select="nomisma:normalizeLabel($num, normalize-space(@letters))"/>
            </col>
            <col>http://nomisma.org/id/price1991</col>
            <col>
                <xsl:value-of select="concat('http://numismatics.org/symbolimages/pella/monogram.price.', $num, '.svg')"/>
            </col>
            <col>http://nomisma.org/editor/pvalfen</col>
            <col>https://orcid.org/0000-0001-7542-4252</col>
        </row>
    </xsl:template>

    <xsl:function name="nomisma:normalizeLabel">
        <xsl:param name="num"/>
        <xsl:param name="letters"/>
        
        <xsl:variable name="letter-sequence" select="for $c in string-to-codepoints($letters) return codepoints-to-string($c)"/>

        <!-- definition boilerplate -->
        <xsl:text>Monogram </xsl:text>
        <xsl:value-of select="$num"/>
        <xsl:text> from M.J. Price, Coinage in the Name of Alexander the Great and Philip Arrhidaeus: A British Museum Catalogue.</xsl:text>
        
        <!-- parse constituent letters -->
        <xsl:text> The monogram contains</xsl:text>
        <xsl:for-each select="$letter-sequence">
            <xsl:if test="position() = last()">
                <xsl:text> and</xsl:text>
            </xsl:if>
            <xsl:text> </xsl:text>
            <xsl:value-of select="."/>
            <xsl:if test="not(position() = last()) and (count($letter-sequence) &gt; 2)">
                <xsl:text>,</xsl:text>
            </xsl:if> 
        </xsl:for-each>
        <xsl:text> as identified by Peter van Alfen.</xsl:text>
    </xsl:function>


</xsl:stylesheet>
