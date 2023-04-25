<?xml version="1.0" encoding="UTF-8"?>
<!-- Author: Ethan Gruber
    Date: April 2023
    Function: Transform the original AOD version of the NUDS into a CSV file that can be cleaned up in OpenRefine prior to revision into new version -->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nuds="http://nomisma.org/nuds"
    xmlns:xlink="http://www.w3.org/1999/xlink" exclude-result-prefixes="#all" version="2.0">

    <xsl:strip-space elements="*"/>
    <!--<xsl:output method="xml" indent="yes"/>-->
    <xsl:output method="text"/>

    <xsl:template match="/">
        <!-- first, get distinct type descriptions -->
        <xsl:variable name="all-types" as="item()">
            <types>
                <xsl:for-each
                    select="collection(iri-to-uri('file:///home/komet/ans_migration/aod/migration/old_nuds?select=*.xml'))//nuds:type/nuds:description">
                    <xsl:variable name="type" select="normalize-space(.)"/>

                    <type>
                        <xsl:attribute name="sortId">
                            <xsl:analyze-string
                                select="ancestor::nuds:nuds/nuds:control/nuds:recordId"
                                regex="([0-9]+)\.([0-9]+)\.([0-9]+)\.?(.*)?">
                                <xsl:matching-substring>
                                    <xsl:value-of select="
                                            concat(format-number(number(regex-group(1)), '0000'), '.', format-number(number(regex-group(2)), '0000'), '.', format-number(number(regex-group(3)), '0000'), if (regex-group(4)) then
                                                concat('.', regex-group(4))
                                            else
                                                '')"/>
                                </xsl:matching-substring>
                                <xsl:non-matching-substring>
                                    <xsl:value-of select="."/>
                                </xsl:non-matching-substring>
                            </xsl:analyze-string>
                        </xsl:attribute>

                        <xsl:attribute name="side">
                            <xsl:choose>
                                <xsl:when test="ancestor::nuds:obverse">obverse</xsl:when>
                                <xsl:when test="ancestor::nuds:reverse">reverse</xsl:when>
                            </xsl:choose>
                        </xsl:attribute>

                        <xsl:value-of select="$type"/>
                    </type>
                </xsl:for-each>
            </types>
        </xsl:variable>


        <xsl:variable name="types" as="item()">
            <types>
                <xsl:for-each select="$all-types//type[@side = 'obverse']">
                    <xsl:sort select="@sortId"/>

                    <xsl:if test="not(. = preceding-sibling::type)">
                        <type>
                            <xsl:attribute name="code"
                                select="concat('O', format-number(position(), '0000'))"/>
                            <xsl:value-of select="."/>
                        </type>
                    </xsl:if>
                </xsl:for-each>
                <xsl:for-each select="$all-types//type[@side = 'reverse']">
                    <xsl:sort select="@sortId"/>

                    <xsl:if test="not(. = preceding-sibling::type)">
                        <type>
                            <xsl:attribute name="code"
                                select="concat('R', format-number(position(), '0000'))"/>
                            <xsl:value-of select="."/>
                        </type>
                    </xsl:if>
                </xsl:for-each>
            </types>
        </xsl:variable>

        <xsl:variable name="row" as="item()">
            <sheet>
                <xsl:for-each
                    select="collection(iri-to-uri('file:///home/komet/ans_migration/aod/migration/old_nuds?select=*.xml'))">
                    <row>
                        <xsl:apply-templates select="/nuds:nuds">
                            <xsl:with-param name="types" select="$types"/>
                        </xsl:apply-templates>
                    </row>
                </xsl:for-each>
            </sheet>
        </xsl:variable>

        <!--<sheet>
            <!-\-<xsl:copy-of select="$types"/>-\->

            <xsl:for-each select="$row//row">
                <xsl:sort select="sortId"/>

                <xsl:copy-of select="."/>
            </xsl:for-each>
        </sheet>-->

        <!-- output the types -->

        <xsl:for-each select="$types//type">

            <xsl:value-of select="@code"/>
            <xsl:text>,"</xsl:text>
            <xsl:value-of select="replace(., '&#x022;', '&#x022;&#x022;')"/>
            <xsl:text>"&#x0A;</xsl:text>
        </xsl:for-each>


    </xsl:template>

    <xsl:template match="nuds:nuds">
        <xsl:param name="types"/>

        <id>
            <xsl:value-of select="nuds:control/nuds:recordId"/>
        </id>
        <parentId>
            <xsl:if test="matches(nuds:control/nuds:recordId, '^.*\.[a-z]$')">
                <xsl:variable name="pieces" select="tokenize(nuds:control/nuds:recordId, '\.')"/>
                
                <xsl:for-each select="$pieces">
                    <xsl:if test="position() &lt; count($pieces)">
                        <xsl:value-of select="."/>
                        
                        <xsl:if test="position() &lt; (count($pieces) - 1)">
                            <xsl:text>.</xsl:text>
                        </xsl:if>
                    </xsl:if>
                </xsl:for-each>
            </xsl:if>
        </parentId>
        <sortId>
            <xsl:analyze-string select="nuds:control/nuds:recordId"
                regex="([0-9]+)\.([0-9]+)\.([0-9]+)\.?(.*)?">
                <xsl:matching-substring>
                    <xsl:value-of select="
                            concat(format-number(number(regex-group(1)), '0000'), '.', format-number(number(regex-group(2)), '0000'), '.', format-number(number(regex-group(3)), '0000'), if (regex-group(4)) then
                                concat('.', regex-group(4))
                            else
                                '')"/>
                </xsl:matching-substring>
                <xsl:non-matching-substring>
                    <xsl:value-of select="."/>
                </xsl:non-matching-substring>
            </xsl:analyze-string>
        </sortId>
        <xsl:apply-templates select="descendant::nuds:typeDesc">
            <xsl:with-param name="types" select="$types"/>
        </xsl:apply-templates>

        <xsl:choose>
            <xsl:when test="descendant::nuds:subjectSet">
                <xsl:apply-templates select="descendant::nuds:subjectSet"/>
            </xsl:when>
            <xsl:otherwise>
                <subjectEvent/>
                <subjectPerson/>
                <subjectPlace/>
            </xsl:otherwise>
        </xsl:choose>

        <xsl:choose>
            <xsl:when test="descendant::nuds:refDesc">
                <xsl:apply-templates select="descendant::nuds:refDesc"/>
            </xsl:when>
            <xsl:otherwise>
                <reference/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template match="nuds:typeDesc">
        <xsl:param name="types"/>

        <xsl:choose>
            <xsl:when test="nuds:date">
                <fromDate>
                    <xsl:value-of select="number(nuds:date/@standardDate)"/>
                </fromDate>
                <toDate>
                    <xsl:value-of select="number(nuds:date/@standardDate)"/>
                </toDate>
            </xsl:when>
            <xsl:when test="nuds:dateRange">
                <fromDate>
                    <xsl:value-of select="number(nuds:dateRange/nuds:fromDate/@standardDate)"/>
                </fromDate>
                <toDate>
                    <xsl:value-of select="number(nuds:dateRange/nuds:toDate/@standardDate)"/>
                </toDate>
            </xsl:when>
            <xsl:otherwise>
                <fromDate/>
                <toDate/>
            </xsl:otherwise>
        </xsl:choose>

        <xsl:choose>
            <xsl:when test="nuds:dateOnObject">
                <dateOnObject>
                    <xsl:value-of select="normalize-space(nuds:dateOnObject)"/>
                </dateOnObject>
            </xsl:when>
            <xsl:otherwise>
                <dateOnObject/>
            </xsl:otherwise>
        </xsl:choose>

        <objectType>
            <xsl:value-of select="normalize-space(nuds:objectType)"/>
        </objectType>

        <manufacture>
            <xsl:choose>
                <xsl:when test="nuds:manufacture/@xlink:href">
                    <xsl:value-of select="nuds:manufacture/@xlink:href"/>
                </xsl:when>
                <xsl:when test="nuds:manufacture[text()]">
                    <xsl:value-of select="normalize-space(nuds:manufacture)"/>
                </xsl:when>
            </xsl:choose>
        </manufacture>

        <material>
            <xsl:choose>
                <xsl:when test="nuds:material/@xlink:href">
                    <xsl:value-of select="nuds:material/@xlink:href"/>
                </xsl:when>
                <xsl:when test="nuds:material[text()]">
                    <xsl:value-of select="normalize-space(nuds:material)"/>
                </xsl:when>
            </xsl:choose>
        </material>

        <denomination>
            <xsl:choose>
                <xsl:when test="nuds:denomination/@xlink:href">
                    <xsl:value-of select="nuds:denomination/@xlink:href"/>
                </xsl:when>
                <xsl:when test="nuds:denomination[text()]">
                    <xsl:value-of select="normalize-space(nuds:denomination)"/>
                </xsl:when>
            </xsl:choose>
        </denomination>

        <mint>
            <xsl:value-of
                select="string-join(nuds:geographic/nuds:geogname[@xlink:role = 'mint'], '|')"/>
        </mint>
        <region>
            <xsl:value-of
                select="string-join(nuds:geographic/nuds:geogname[@xlink:role = 'region'], '|')"/>
        </region>
        <authority>
            <xsl:value-of
                select="string-join(nuds:authority/nuds:persname[@xlink:role = 'authority'], '|')"/>
        </authority>
        <issuer>
            <xsl:value-of
                select="string-join(nuds:authority/nuds:persname[@xlink:role = 'issuer'], '|')"/>
        </issuer>
        <artist>
            <xsl:value-of
                select="string-join(nuds:authority/nuds:persname[@xlink:role = 'artist'], '|')"/>
        </artist>
        <maker>
            <xsl:value-of
                select="string-join(nuds:authority/nuds:persname[@xlink:role = 'maker'], '|')"/>
        </maker>

        <obverse_type>
            <xsl:variable name="obverse_type"
                select="normalize-space(nuds:obverse/nuds:type/nuds:description)"/>

            <xsl:value-of select="$types//type[. = $obverse_type]/@code"/>
        </obverse_type>

        <obverse_legend>
            <xsl:value-of select="normalize-space(nuds:obverse/nuds:legend)"/>
        </obverse_legend>

        <obverse_portrait>
            <xsl:value-of
                select="string-join(nuds:obverse/nuds:persname[@xlink:role = 'deity'], '|')"/>
        </obverse_portrait>

        <reverse_type>
            <xsl:variable name="reverse_type"
                select="normalize-space(nuds:reverse/nuds:type/nuds:description)"/>

            <xsl:value-of select="$types//type[. = $reverse_type]/@code"/>
        </reverse_type>

        <reverse_legend>
            <xsl:value-of select="normalize-space(nuds:reverse/nuds:legend)"/>
        </reverse_legend>

        <reverse_portrait>
            <xsl:value-of
                select="string-join(nuds:reverse/nuds:persname[@xlink:role = 'deity'], '|')"/>
        </reverse_portrait>

    </xsl:template>

    <xsl:template match="nuds:subjectSet">
        <subjectEvent>
            <xsl:value-of select="string-join(nuds:subject[@localType = 'subjectEvent'], '|')"/>
        </subjectEvent>
        <subjectPerson>
            <xsl:value-of select="string-join(nuds:subject[@localType = 'subjectPerson'], '|')"/>
        </subjectPerson>
        <subjectPlace>
            <xsl:value-of select="string-join(nuds:subject[@localType = 'subjectPlace'], '|')"/>
        </subjectPlace>
    </xsl:template>

    <xsl:template match="nuds:refDesc">
        <reference>
            <xsl:value-of select="string-join(nuds:reference, '|')"/>
        </reference>

    </xsl:template>


</xsl:stylesheet>
