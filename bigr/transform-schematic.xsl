<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nuds="http://nomisma.org/nuds"
    xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns="http://www.tei-c.org/ns/1.0" xmlns:res="http://www.w3.org/2005/sparql-results#"
    xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:numishare="https://github.com/ewg118/numishare" exclude-result-prefixes="xs res nuds xlink tei numishare"
    version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output encoding="UTF-8" indent="yes" method="xml"/>

    <xsl:variable name="sparql_query">http://nomisma.org/query</xsl:variable>
    <xsl:variable name="type_series_uri" select="descendant::tei:ref[@type = 'typeSeries']/@target"/>
    <xsl:variable name="uri_space" select="descendant::tei:ref[@type = 'uriSpace']/@target"/>

    <!-- move to config variables -->
    <xsl:variable name="titleReplace">
        <xsl:text>Bactrian and Indo-Greek Coinage </xsl:text>
    </xsl:variable>

    <!-- issue a getNUDS API call to project, aggregating the type and subtype NUDS into one document -->
    <xsl:variable name="nudsGroup" as="element()*">
        <nudsGroup>


            <xsl:variable name="id-param">
                <xsl:for-each select="descendant::tei:item">
                    <xsl:variable name="id" select="normalize-space(.)"/>
                    <xsl:variable name="uri" select="concat($uri_space, $id)"/>
                    <xsl:variable name="query" select="replace(//tei:code[@xml:id = 'subtypes-sparql'], '%TYPE%', $uri)"/>

                    <xsl:value-of select="$id"/>

                    <xsl:for-each select="document(concat($sparql_query, '?query=', encode-for-uri($query), '&amp;output=xml'))//res:binding[@name= 'subtype']">
                        <xsl:text>|</xsl:text>
                        <xsl:value-of select="substring-after(res:uri, 'id/')"/>
                    </xsl:for-each>

                    <xsl:if test="not(position() = last())">
                        <xsl:text>|</xsl:text>
                    </xsl:if>
                </xsl:for-each>
            </xsl:variable>

            <xsl:for-each select="document(concat($type_series_uri, 'apis/getNuds?identifiers=', encode-for-uri($id-param)))//nuds:nuds">
                <object xlink:href="{$type_series_uri}id/{nuds:control/nuds:recordId}">
                    <xsl:copy-of select="."/>
                </object>
            </xsl:for-each>
        </nudsGroup>
    </xsl:variable>


    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="tei:list">

        <!-- display the Authority and Date only for the top of the section -->
        <xsl:apply-templates select="$nudsGroup//*[local-name() = 'object'][@xlink:href = concat($uri_space, tei:item[1])]/nuds:nuds" mode="section-header"/>

        <xsl:element name="table" namespace="http://www.tei-c.org/ns/1.0">
            <xsl:apply-templates select="tei:item"/>
        </xsl:element>
    </xsl:template>

    <xsl:template match="tei:item">
        <xsl:variable name="id" select="normalize-space(.)"/>
        <xsl:variable name="uri" select="concat($uri_space, $id)"/>

        <xsl:apply-templates select="$nudsGroup//*[local-name() = 'object'][@xlink:href = $uri]/nuds:nuds" mode="type"/>
    </xsl:template>



    <!-- Structuring NUDS/XML into TEI prose -->
    <xsl:template match="nuds:nuds" mode="section-header">
        <head>
            <xsl:value-of select="descendant::nuds:title"/>
        </head>
        <ab>
            <xsl:choose>
                <xsl:when test="nuds:typeDesc/nuds:date[@notBefore and @notAfter]">
                    <xsl:value-of select="nuds:typeDesc/nuds:date"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:choose>
                        <xsl:when test="nuds:typeDesc/nuds:date[@standardDate]">
                            <xsl:value-of select="numishare:normalizeDate(nuds:typeDesc/nuds:date/@standardDate)"/>
                        </xsl:when>
                        <xsl:when test="nuds:dateRange">
                            <xsl:value-of select="numishare:normalizeDate(nuds:typeDesc/nuds:dateRange/nuds:fromDate/@standardDate)"/>
                            <xsl:text> - </xsl:text>
                            <xsl:value-of select="numishare:normalizeDate(nuds:typeDesc/nuds:dateRange/nuds:toDate/@standardDate)"/>
                        </xsl:when>
                    </xsl:choose>
                </xsl:otherwise>

            </xsl:choose>
        </ab>
    </xsl:template>

    <!-- transforming parent type metadata -->
    <xsl:template match="nuds:nuds" mode="type">
        <row>
            <cell>
                <xsl:apply-templates select="descendant::nuds:typeDesc" mode="type"/>
            </cell>
        </row>
    </xsl:template>

    <xsl:template match="nuds:typeDesc" mode="type">
        <p>
            <xsl:value-of select="replace(parent::nuds:descMeta/nuds:title[@xml:lang = 'en'], $titleReplace, '')"/>
            <xsl:text> </xsl:text>
            <xsl:apply-templates select="nuds:material"/>
            <xsl:apply-templates select="nuds:shape"/>
            <xsl:apply-templates select="nuds:denomination"/>
        </p>

        <!-- obverse and reverse -->
        <xsl:apply-templates select="nuds:obverse | nuds:reverse" mode="type"/>
    </xsl:template>

    <xsl:template match="nuds:denomination | nuds:material | nuds:shape">
        <xsl:value-of select="."/>
        <xsl:text> </xsl:text>
    </xsl:template>

    <xsl:template match="nuds:obverse | nuds:reverse" mode="type">
        <p rend="indent">
            <xsl:value-of select="concat(upper-case(substring(local-name(), 1, 1)), substring(local-name(), 2, 2))"/>
            <xsl:text>: </xsl:text>
            <xsl:apply-templates select="nuds:legend"/>
            <lb/>
            <xsl:apply-templates select="nuds:type"/>
        </p>
    </xsl:template>
    
    <xsl:template match="nuds:legend">
        <xsl:choose>
            <xsl:when test="child::tei:div">
                <xsl:apply-templates select="tei:div[@type = 'edition']"/>
                <xsl:apply-templates select="tei:div[@type = 'transliteration']"/>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="."/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>    
    
    <xsl:template match="nuds:type">
        <xsl:value-of select="nuds:description[@xml:lang = 'en']"/>
    </xsl:template>
    
    <!-- handling of EpiDoc TEI in legends -->
    <xsl:template match="tei:div[@type = 'edition']">
        <xsl:apply-templates/>
    </xsl:template>
    
    <xsl:template match="tei:div[@type = 'transliteration']">
        <xsl:text> </xsl:text>
        <hi rend="italic">
            <xsl:apply-templates/>
        </hi>  
    </xsl:template>
    
    <xsl:template match="tei:ab">
        <xsl:apply-templates/>
        <xsl:if test="@rend">
            <xsl:text> </xsl:text>
            <xsl:value-of select="@rend"/>            
        </xsl:if>
    </xsl:template>
    
    <xsl:template match="tei:gap">
        <xsl:text>[gap: </xsl:text>
        <i>
            <xsl:value-of select="@reason"/>
        </i>
        <xsl:text>]</xsl:text>
    </xsl:template>
    
    <xsl:template match="tei:space">
        <xsl:text>[intentional space]</xsl:text>
    </xsl:template>
    
    <!-- rendering -->
    <xsl:template match="tei:hi[@rend]">
        <xsl:choose>
            <xsl:when test="@rend = 'ligature'">
                <xsl:call-template name="ligaturizeText">
                    <xsl:with-param name="textLigaturize" select="normalize-space(.)"/>
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="."/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
    
    <!-- template from EpiDoc: https://github.com/EpiDoc/Stylesheets/blob/master/teihi.xsl -->
    <xsl:template name="ligaturizeText">
        <xsl:param name="textLigaturize"/>
        <xsl:analyze-string select="$textLigaturize" regex="\p{{L}}">
            <!-- select letters only (will omit combining chars) -->
            <xsl:matching-substring>
                <xsl:choose>
                    <xsl:when test="position() = 1">
                        <!-- skip first ligatured char -->
                        <xsl:value-of select="."/>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:text>&#x0361;</xsl:text>
                        <!-- emit ligature combining char -->
                        <xsl:value-of select="."/>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:matching-substring>
            <xsl:non-matching-substring>
                <xsl:value-of select="."/>
            </xsl:non-matching-substring>
        </xsl:analyze-string>
    </xsl:template>


    <!-- FUNCTIONS -->
    <xsl:function name="numishare:normalizeDate">
        <xsl:param name="date"/>

        <!--<xsl:if test="substring($date, 1, 1) != '-' and number(substring($date, 1, 4)) &lt;= 400">
			<xsl:text>A.D. </xsl:text>
		</xsl:if>-->

        <xsl:choose>
            <xsl:when test="$date castable as xs:dateTime">
                <xsl:value-of select="format-dateTime($date, '[D] [MNn] [Y], [H01]:[m01]')"/>
            </xsl:when>
            <xsl:when test="$date castable as xs:date">
                <xsl:value-of select="format-date($date, '[D] [MNn] [Y]')"/>
            </xsl:when>
            <xsl:when test="$date castable as xs:gYearMonth">
                <xsl:variable name="normalized" select="xs:date(concat($date, '-01'))"/>
                <xsl:value-of select="format-date($normalized, '[MNn] [Y]')"/>
            </xsl:when>
            <xsl:when test="$date castable as xs:gYear or $date castable as xs:integer">
                <xsl:value-of select="abs(number($date))"/>
            </xsl:when>
        </xsl:choose>

        <xsl:if test="substring($date, 1, 1) = '-'">
            <xsl:text> BCE</xsl:text>
        </xsl:if>
    </xsl:function>

</xsl:stylesheet>
