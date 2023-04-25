<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nuds="http://nomisma.org/nuds"
    xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns="http://www.tei-c.org/ns/1.0" xmlns:res="http://www.w3.org/2005/sparql-results#"
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:skos="http://www.w3.org/2004/02/skos/core#" xmlns:crm="http://www.cidoc-crm.org/cidoc-crm/"
    xmlns:crmdig="http://www.ics.forth.gr/isl/CRMdig/" xmlns:nmo="http://nomisma.org/ontology#" xmlns:xlink="http://www.w3.org/1999/xlink"
    xmlns:numishare="https://github.com/ewg118/numishare" exclude-result-prefixes="xs res nuds xlink tei numishare rdf skos crm crmdig nmo" version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output encoding="UTF-8" indent="yes" method="xml"/>

    <xsl:variable name="sparql_query">http://nomisma.org/query</xsl:variable>
    <xsl:variable name="type_series_uri" select="descendant::tei:ref[@type = 'typeSeries']/@target"/>
    <xsl:variable name="uri_space" select="descendant::tei:ref[@type = 'uriSpace']/@target"/>

    <!-- SPARQL queries -->
    <xsl:variable name="specimens-sparql" select="descendant::tei:code[@xml:id = 'specimens-sparql']"/>

    <!--config variables -->
    <xsl:variable name="config" as="node()*">
        <xsl:copy-of select="document('config.xml')"/>        
    </xsl:variable>
    
    <xsl:variable name="titleReplace" select="$config/config/titleReplace"/>    

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

    <xsl:variable name="rdf" as="element()*">
        <rdf:RDF xmlns:dcterms="http://purl.org/dc/terms/" xmlns:nm="http://nomisma.org/id/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
            xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#" xmlns:skos="http://www.w3.org/2004/02/skos/core#"
            xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#" xmlns:foaf="http://xmlns.com/foaf/0.1/" xmlns:org="http://www.w3.org/ns/org#"
            xmlns:nomisma="http://nomisma.org/" xmlns:nmo="http://nomisma.org/ontology#">

            <xsl:for-each
                select="
                    distinct-values($nudsGroup/descendant::nuds:symbol[matches(@xlink:href, 'https?://numismatics\.org')]/@xlink:href | $nudsGroup/descendant::nuds:symbol/descendant::tei:g[matches(@ref, 'https?://numismatics\.org')]/@ref)">
                <xsl:variable name="href" select="."/>

                <xsl:if test="doc-available(concat($href, '.rdf'))">
                    <xsl:copy-of select="document(concat($href, '.rdf'))/rdf:RDF/*"/>
                </xsl:if>
            </xsl:for-each>
        </rdf:RDF>
    </xsl:variable>


    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="tei:list">

        <!-- display the Authority and Date only for the top of the section -->
        <xsl:apply-templates select="$nudsGroup//*[local-name() = 'object'][@xlink:href = concat($uri_space, tei:item[1])]/nuds:nuds" mode="section-header"/>

        <table>
            <xsl:apply-templates select="tei:item"/>
        </table>

        <!--<xsl:copy-of select="$rdf"/>-->
    </xsl:template>

    <xsl:template match="tei:item">
        <xsl:variable name="id" select="normalize-space(.)"/>
        <xsl:variable name="uri" select="concat($uri_space, $id)"/>

        <xsl:variable name="images" as="node()*">
            <index>
                <xsl:copy-of select="//tei:index[@indexName = 'photographs']/tei:term[@xml:id = $id]"/>
            </index>
        </xsl:variable>

        <!-- apply parent type template -->
        <row>
            <cell>
                <xsl:apply-templates select="$nudsGroup//*[local-name() = 'object'][@xlink:href = $uri]/nuds:nuds" mode="type"/>
            </cell>
            <cell style="width:2in"/>
        </row>

        <!-- apply templates for each subtype -->
        <xsl:if test="$nudsGroup//nuds:nuds[nuds:control/nuds:otherRecordId[@semantic = 'skos:broader'] = $id]">
            <xsl:apply-templates select="$nudsGroup//nuds:nuds[nuds:control/nuds:otherRecordId[@semantic = 'skos:broader'] = $id]" mode="subtype">
                <xsl:with-param name="images" select="$images" as="node()*"/>
            </xsl:apply-templates>
        </xsl:if>


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

    <!-- *** PARENT TYPES *** -->
    <xsl:template match="nuds:nuds" mode="type">
        <xsl:apply-templates select="descendant::nuds:typeDesc" mode="type"/>
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

    <!-- *** SUBTYPES *** -->
    <xsl:template match="nuds:nuds" mode="subtype">
        <xsl:param name="images"/>

        <xsl:variable name="id" select="nuds:control/nuds:recordId"/>

        <xsl:variable name="titlePieces" select="tokenize($id, '\.')"/>

        <!-- execute SPARQL query to get a list of all associated speciments -->
        <xsl:variable name="uri" select="concat($uri_space, $id)"/>
        <xsl:variable name="query" select="replace($specimens-sparql, '%TYPE%', $uri)"/>

        <xsl:variable name="results" as="node()*">
            <xsl:copy-of select="document(concat($sparql_query, '?query=', encode-for-uri($query), '&amp;output=xml'))"/>
        </xsl:variable>

        <row>
            <cell>
                <p rend="indent">
                    <xsl:value-of select="concat($titlePieces[3], '.', $titlePieces[4])"/>
                    <xsl:text> </xsl:text>
                    <xsl:apply-templates select="nuds:descMeta/nuds:typeDesc/nuds:reverse/nuds:symbol"/>
                </p>

                <xsl:if test="count($results//res:result) &gt; 0">
                    <list>
                        <xsl:apply-templates select="$results//res:result" mode="specimens"/>
                    </list>
                </xsl:if>
            </cell>

            <cell style="width:2in">
                <xsl:if test="string($images//tei:term[@xml:id = $id])">
                    <xsl:variable name="coinURI" select="$images//tei:term[@xml:id = $id]"/>

                    <xsl:apply-templates select="$results//res:result[res:binding[@name = 'coin']/res:uri = $coinURI]" mode="images"/>
                </xsl:if>
            </cell>
        </row>

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
            <seg type="orientation">
                <xsl:text> </xsl:text>
                <xsl:value-of select="@rend"/>
            </seg>           
        </xsl:if>
    </xsl:template>

    <xsl:template match="tei:gap">
        <xsl:text>[gap: </xsl:text>
        <hi rend="italic">
            <xsl:value-of select="@reason"/>
        </hi>
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

    <!-- handling of EpiDoc TEI in symbols -->
    <xsl:template match="nuds:symbol">
        <xsl:apply-templates mode="symbols">
            <xsl:with-param name="position" select="@position"/>
        </xsl:apply-templates>

        <xsl:text> (</xsl:text>
        <xsl:value-of select="numishare:normalizePosition(@position)"/>
        <xsl:text>)</xsl:text>
    </xsl:template>

    <xsl:template match="tei:div" mode="symbols">
        <xsl:param name="position"/>

        <xsl:apply-templates select="tei:choice | tei:ab" mode="symbols">
            <xsl:with-param name="position" select="$position"/>
        </xsl:apply-templates>
    </xsl:template>

    <xsl:template match="tei:ab" mode="symbols">
        <xsl:param name="position"/>

        <xsl:choose>
            <xsl:when test="child::*">
                <xsl:apply-templates select="*" mode="symbols">
                    <xsl:with-param name="position" select="$position"/>
                </xsl:apply-templates>
            </xsl:when>
            <xsl:when test="string-length(normalize-space(.)) &gt; 0">
                <xsl:apply-templates select="text()" mode="symbols">
                    <xsl:with-param name="position" select="$position"/>
                </xsl:apply-templates>
            </xsl:when>
        </xsl:choose>

        <xsl:if test="@rend">
            <xsl:text> (</xsl:text>
            <hi rend="italics">
                <xsl:value-of select="@rend"/>
            </hi>
            <xsl:text>)</xsl:text>
        </xsl:if>

        <xsl:if test="not(position() = last())">
            <xsl:text> / </xsl:text>
        </xsl:if>
    </xsl:template>

    <xsl:template match="tei:space" mode="symbols">
        <xsl:text>[no monogram]</xsl:text>
    </xsl:template>

    <xsl:template match="tei:seg | tei:am | tei:g" mode="symbols">
        <xsl:param name="position"/>

        <xsl:choose>
            <xsl:when test="self::tei:g and matches(@ref, '^https?://numismatics\.org')">
                <xsl:variable name="href" select="@ref"/>
                <xsl:apply-templates select="$rdf/*[@rdf:about = $href]"/>
            </xsl:when>
            <xsl:when test="child::*">
                <xsl:apply-templates select="*" mode="symbols">
                    <xsl:with-param name="position" select="$position"/>
                </xsl:apply-templates>
            </xsl:when>
            <xsl:when test="string-length(normalize-space(.)) &gt; 0">
                <xsl:value-of select="."/>
            </xsl:when>
        </xsl:choose>

        <xsl:if test="@rend">
            <hi rend="italic">
                <xsl:text> (</xsl:text>
                <xsl:value-of select="@rend"/>
                <xsl:text>)</xsl:text>
            </hi>
        </xsl:if>

        <xsl:if test="tei:unclear">
            <hi rend="italic"> (unclear)</hi>
        </xsl:if>

        <xsl:if test="not(position() = last())">
            <hi rend="italic"> beside </hi>
        </xsl:if>
    </xsl:template>

    <xsl:template match="tei:choice" mode="symbols">
        <xsl:param name="position"/>

        <xsl:for-each select="*">
            <xsl:apply-templates select="self::node()" mode="symbols">
                <xsl:with-param name="position" select="$position"/>
            </xsl:apply-templates>
            <xsl:if test="not(position() = last())">
                <hi rend="italic"> or </hi>
            </xsl:if>
        </xsl:for-each>
    </xsl:template>

    <!-- *************** RENDER RDF ABOUT SYMBOLS ******************-->
    <xsl:template match="nmo:Monogram | crm:E37_Mark">
        <xsl:apply-templates select="descendant::crmdig:D1_Digital_Object">
            <xsl:with-param name="uri" select="@rdf:about"/>
            <xsl:with-param name="label" select="skos:prefLabel[@xml:lang = 'en']"/>
            <xsl:with-param name="type" select="name()"/>
        </xsl:apply-templates>

        <!-- Unicode characters -->
        <xsl:if test="crm:P165i_is_incorporated_in[string(.) and not(child::*)]">
            <xsl:text>, represents </xsl:text>
            <xsl:value-of select="crm:P165i_is_incorporated_in[string(.) and not(child::*)]"/>
        </xsl:if>
    </xsl:template>

    <xsl:template match="crmdig:D1_Digital_Object">
        <xsl:param name="uri"/>
        <xsl:param name="label"/>
        <xsl:param name="type"/>

        <xsl:text> </xsl:text>
        <ref target="{$uri}">
            <figure rend="thumbnail" place="inline" type="{$type}">
                <graphic url="{@rdf:about}" mimeType="image/svg+xml"/>
                <figDesc>
                    <xsl:value-of select="$label"/>
                </figDesc>
            </figure>
        </ref>
        <xsl:if test="not(position() = last())">
            <xsl:text> -</xsl:text>
        </xsl:if>

    </xsl:template>


    <!-- ***** SUPPRESS SCHEMANTIC TEI ELEMENT ***** -->
    <xsl:template match="tei:code"/>

    <!-- *************** LIST SPECIMENS FROM SPARQL ******************-->
    <xsl:template match="res:result" mode="specimens">
        <item>
            <ref target="{res:binding[@name = 'coin']/res:uri}">
                <xsl:choose>
                    <xsl:when test="res:binding[@name = 'collection']">
                        <xsl:value-of select="res:binding[@name = 'collection']/res:literal"/>
                        <xsl:text> </xsl:text>
                    </xsl:when>
                </xsl:choose>
                <xsl:value-of select="res:binding[@name = 'identifier']/res:literal"/>
            </ref>
            <xsl:if test="res:binding[@name = 'weight']">
                <xsl:text>, </xsl:text>
                <xsl:value-of select="res:binding[@name = 'weight']/res:literal"/>
                <xsl:text> g</xsl:text>
            </xsl:if>
        </item>
    </xsl:template>

    <!-- *************** DISPLAY EXEMPLAR IMAGES ******************-->
    <xsl:template match="res:result" mode="images">
        
        <xsl:variable name="label">
            <xsl:choose>
                <xsl:when test="res:binding[@name = 'collection']">
                    <xsl:value-of select="res:binding[@name = 'collection']/res:literal"/>
                    <xsl:text> </xsl:text>
                </xsl:when>
            </xsl:choose>
            <xsl:value-of select="res:binding[@name = 'identifier']/res:literal"/>
        </xsl:variable>
        
        <xsl:choose>
            <!-- handle obverse and reverse IIIF images -->
            <xsl:when test="string(res:binding[@name = 'obvManifest']) and string(res:binding[@name = 'revManifest'])">
                <xsl:variable name="obvImage" select="replace(res:binding[@name = 'obvManifest']/res:uri, '/info.json', '')"/>
                <xsl:variable name="revImage" select="replace(res:binding[@name = 'revManifest']/res:uri, '/info.json', '')"/>

                <figure type="IIIF" rend="sideImage">
                    <graphic url="{$obvImage}" mimeType="image/jpeg"/>
                    <figDesc>
                        <xsl:text>Obverse of </xsl:text>
                        <xsl:value-of select="$label"/>
                    </figDesc>
                </figure>
                <figure type="IIIF" rend="sideImage">
                    <graphic url="{$revImage}" mimeType="image/jpeg"/>
                    <figDesc>
                        <xsl:text>Reverse of </xsl:text>
                        <xsl:value-of select="$label"/>
                    </figDesc>
                </figure>
            </xsl:when>
            <xsl:when test="string(res:binding[@name = 'comManifest'])">    
                <xsl:variable name="image" select="replace(res:binding[@name = 'comManifest']/res:uri, '/info.json', '')"/>
                
                <figure type="IIIF" rend="combinedImage">
                    <graphic url="{$image}" mimeType="image/jpeg"/>
                    <figDesc>
                        <xsl:text>Image of </xsl:text>
                        <xsl:value-of select="$label"/>
                    </figDesc>
                </figure>                
            </xsl:when>
            <xsl:when test="string(res:binding[@name = 'obvRef']) and string(res:binding[@name = 'revRef'])">
                <xsl:variable name="obvImage" select="res:binding[@name = 'obvRef']/res:uri"/>
                <xsl:variable name="revImage" select="res:binding[@name = 'revRef']/res:uri"/>
                
                <figure rend="sideImage">
                    <graphic url="{$obvImage}" mimeType="image/jpeg"/>
                    <figDesc>
                        <xsl:text>Obverse of </xsl:text>
                        <xsl:value-of select="$label"/>
                    </figDesc>
                </figure>
                <figure rend="sideImage">
                    <graphic url="{$revImage}" mimeType="image/jpeg"/>
                    <figDesc>
                        <xsl:text>Reverse of </xsl:text>
                        <xsl:value-of select="$label"/>
                    </figDesc>
                </figure>
            </xsl:when>
            <xsl:when test="string(res:binding[@name = 'comRef'])">                
                <figure rend="combinedImage">
                    <graphic url="{res:binding[@name = 'comRef']/res:uri}" mimeType="image/jpeg"/>
                    <figDesc>
                        <xsl:text>Image of </xsl:text>
                        <xsl:value-of select="$label"/>
                    </figDesc>
                </figure>                
            </xsl:when>
        </xsl:choose>
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
    
    <xsl:function name="numishare:normalizePosition">
        <xsl:param name="position"/>
        
        <xsl:choose>
            <xsl:when test="$config//position[@value = $position]/label[@lang = 'en']">
                <xsl:value-of select="$config//position[@value = $position]/label[@lang = 'en']"/>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="concat(upper-case(substring($position, 1, 1)), substring($position, 2))"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:function>

</xsl:stylesheet>
