<?xml version="1.0" encoding="UTF-8"?>

<!-- Author: Ethan Gruber
    Date: July 2020
    Function: Update the NUDS model for symbols with URIs -->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns:nuds="http://nomisma.org/nuds"
    xmlns="http://nomisma.org/nuds" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xlink="http://www.w3.org/1999/xlink"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" exclude-result-prefixes="nuds" version="2.0">
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>


    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="nuds:nuds">
        <nuds recordType="conceptual">
            <xsl:apply-templates/>
        </nuds>
    </xsl:template>


    <xsl:template match="nuds:symbol[@xlink:href]">
        <symbol xlink:type="simple" xlink:href="{@xlink:href}" xlink:arcrole="nmo:hasControlmark">
            <xsl:value-of select="."/>
        </symbol>
    </xsl:template>

    <xsl:template match="nuds:symbol[contains(., 'siscia.off')]">
        <xsl:choose>
            <xsl:when test="contains(., '|')">
                <xsl:variable name="symbols" select="tokenize(., '\|')"/>

                <symbol localType="officinaMark">
                    <tei:div type="edition">
                        <xsl:for-each select="$symbols">
                            <tei:ab>
                                <xsl:choose>
                                    <xsl:when test="contains(., 'siscia.off')">
                                        <xsl:variable name="pieces" select="tokenize(., '\.')"/>
                                        <xsl:if test="number($pieces[last()]) &lt;= 5">
                                            <tei:am>
                                                <tei:g type="nmo:Monogram" ref="http://numismatics.org/ocre/symbol/{.}">
                                                    <xsl:text>Siscia Officina Mark </xsl:text>
                                                    <xsl:value-of select="$pieces[last()]"/>
                                                </tei:g>
                                            </tei:am>
                                        </xsl:if>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <tei:seg>
                                            <xsl:value-of select="."/>
                                        </tei:seg>
                                    </xsl:otherwise>
                                </xsl:choose>

                            </tei:ab>
                        </xsl:for-each>
                    </tei:div>
                </symbol>
            </xsl:when>
            <xsl:otherwise>
                <xsl:variable name="pieces" select="tokenize(., '\.')"/>
                <xsl:if test="number($pieces[last()]) &lt;= 5">
                    <symbol xlink:type="simple" xlink:href="http://numismatics.org/ocre/symbol/{.}" xlink:arcrole="nmo:hasControlmark" localType="officinaMark">
                        <xsl:text>Siscia Officina Mark </xsl:text>
                        <xsl:value-of select="$pieces[last()]"/>
                    </symbol>
                </xsl:if>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>


</xsl:stylesheet>
