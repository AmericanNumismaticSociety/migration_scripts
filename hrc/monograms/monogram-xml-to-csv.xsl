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
                    <col>Parent ID</col>
                    <col>Source</col>
                    <col>Image1</col>
                    <col>Contributor</col>
                    <col>Image Creator</col>
                </row>
                <xsl:apply-templates select="//folder[@name = 'OXUS-INDUS']/file"/>
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
        <xsl:variable name="pieces" select="tokenize(@name, '\.')"/>
        <xsl:variable name="auth">
            <xsl:choose>
                <xsl:when test="$pieces[2] = 'kharoshthi'">Kharoshthi</xsl:when>
                <xsl:when test="$pieces[2] = 'apollodotus_ii'">Apollodotus II</xsl:when>
                <xsl:when test="$pieces[2] = 'bop'">Bopearachchi</xsl:when>
                <xsl:when test="$pieces[2] = 'hippostratus'">Hippostratus</xsl:when>
                <xsl:when test="$pieces[2] = 'PMC'">PMC</xsl:when>
                <xsl:when test="$pieces[2] = 'zoilus_i'">Zoilus I</xsl:when>
                <xsl:otherwise>Other</xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="num" select="$pieces[3]"/>
        
        <!-- for the first letter subtype, create the parent type -->
        
        <xsl:if test="ends-with($num, '_1')">
            <row>
                <col>
                    <xsl:value-of select="concat($pieces[1], '.', $pieces[2], '.', substring-before($num, '_'))"/>
                </col>
                <col>
                    <xsl:value-of select="normalize-space(@letters)"/>
                </col>
                <col>
                    <xsl:value-of select="$auth"/>
                    <xsl:choose>
                        <xsl:when test="not($auth = 'Kharoshthi')">
                            <xsl:text> Monogram </xsl:text>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:text> </xsl:text>
                        </xsl:otherwise>
                    </xsl:choose>
                    
                    <xsl:value-of select="substring-before($num, '_')"/>
                </col>
                <col>
                    <xsl:value-of select="nomisma:normalizeLabel($auth, substring-before($num, '_'), normalize-space(@letters))"/>
                </col>
                <col/>
                <col>
                    <xsl:choose>
                        <xsl:when test="$auth = 'Bopearachchi'">http://nomisma.org/id/bopearachchi-1991</xsl:when>
                        <xsl:otherwise>http://nomisma.org/id/bigr</xsl:otherwise>
                    </xsl:choose>
                </col>
                <col/>
                <col>
                    <xsl:value-of select="concat('http://nomisma.org/editor/', @editor)"/>
                </col>
                <col/>
            </row>
            
        </xsl:if>

        <row>
            <col>
                <xsl:value-of select="substring-before(@name, '.svg')"/>
            </col>
            <col>
                <xsl:value-of select="normalize-space(@letters)"/>
            </col>
            <col>
                <xsl:value-of select="$auth"/>
                <xsl:choose>
                    <xsl:when test="not($auth = 'Kharoshthi')">
                        <xsl:text> Monogram </xsl:text>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:text> </xsl:text>
                    </xsl:otherwise>
                </xsl:choose>

                <xsl:value-of select="replace($num, '_', '.')"/>
            </col>
            <col>
                <xsl:value-of select="nomisma:normalizeLabel($auth, $num, normalize-space(@letters))"/>
            </col>
            <col>
                <xsl:if test="matches($num, '.*_[1-9]')">
                    <xsl:value-of select="concat($pieces[1], '.', $pieces[2], '.', substring-before($num, '_'))"/>
                </xsl:if>
            </col>
            <col>
                <xsl:choose>
                    <xsl:when test="$auth = 'Bopearachchi'">http://nomisma.org/id/bopearachchi-1991</xsl:when>
                    <xsl:otherwise>http://nomisma.org/id/bigr</xsl:otherwise>
                </xsl:choose>
            </col>
            <col>
                <xsl:value-of select="concat('https://numismatics.org/symbolimages/bigr/', @name)"/>
            </col>
            <col>
                <xsl:value-of select="concat('http://nomisma.org/editor/', @editor)"/>
            </col>
            <col>http://nomisma.org/editor/ltomanelli</col>
        </row>
    </xsl:template>

    <xsl:function name="nomisma:normalizeLabel">
        <xsl:param name="auth"/>
        <xsl:param name="num"/>
        <xsl:param name="letters"/>

        <xsl:variable name="letter-sequence"
            select="
                for $c in string-to-codepoints($letters)
                return
                    codepoints-to-string($c)"/>

        <!-- definition boilerplate -->
        <xsl:value-of select="$auth"/>
        <xsl:text> </xsl:text>
        <xsl:value-of select="replace($num, '_', '.')"/>

        <xsl:choose>
            <xsl:when test="$auth = 'Bopearachchi'">
                <xsl:text> from Monnaies gréco-bactriennes et indo-grecques : catalogue raisonné by Osmund Bopearachchi. Bibliothèque Nationale de France, 1991.</xsl:text>
            </xsl:when>
            <xsl:otherwise>
                <xsl:text> from Bactrian and Indo-Greek Coinage Online.</xsl:text>
            </xsl:otherwise>
        </xsl:choose>

        <!-- parse constituent letters -->
        <xsl:choose>
            <xsl:when test="$auth = 'Kharoshthi'">
               <!-- <xsl:text> The symbol is transcribed into UTF-8 characters </xsl:text>
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
                <xsl:text> as identified by Gunnar Dumke.</xsl:text>-->
            </xsl:when>
            <xsl:otherwise>
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
            </xsl:otherwise>
        </xsl:choose>
    </xsl:function>


</xsl:stylesheet>
