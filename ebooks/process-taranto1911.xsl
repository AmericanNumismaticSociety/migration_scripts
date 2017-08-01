<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns:res="http://www.w3.org/2005/sparql-results#" exclude-result-prefixes="#all"
    version="2.0">
    
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>
    
    <xsl:template match="@*|*|processing-instruction()|comment()">
        <xsl:copy>
            <xsl:apply-templates select="*|@*|text()|processing-instruction()|comment()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="tei:records">
        <table xmlns="http://www.tei-c.org/ns/1.0">
            <row role="label">
                <cell/>
                <cell>No.</cell>
                <cell>B.M. Acc.</cell>
                <cell>Weight</cell>
                <cell>L. (mm)</cell>
                <cell>W. (mm)</cell>
                <cell>Th. (mm)</cell>
                <cell>Ingot Type</cell>
                <cell>Description</cell>
            </row>
            <xsl:apply-templates select="tei:record"/>
        </table>
    </xsl:template>
    
    <xsl:template match="tei:record">
        <row xmlns="http://www.tei-c.org/ns/1.0">
            <cell>
                <xsl:apply-templates select="tei:images/tei:image"/>
            </cell>
            <xsl:for-each select="*[not(self::tei:images)]">
                <cell n="{local-name()}">
                    <xsl:choose>
                        <xsl:when test="local-name() = 'bmaccnumber'">
                            <xsl:variable name="regno" select="normalize-space(.)"/>
                            
                            <xsl:if test="document('file:///home/komet/Downloads/sparql.xml')//res:result[res:binding[@name='regno']/res:literal = $regno]">
                                <xsl:variable name="uri" select="document('file:///home/komet/Downloads/sparql.xml')//res:result[res:binding[@name='regno']/res:literal = $regno]/res:binding[@name='object']/res:uri"/>
                                
                                <xsl:choose>
                                    <xsl:when test="string($uri)">
                                        <ref target="{$uri}" xmlns="http://www.tei-c.org/ns/1.0"><xsl:value-of select="$regno"/></ref>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:value-of select="$regno"/>
                                    </xsl:otherwise>
                                </xsl:choose>
                                
                            </xsl:if>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:apply-templates/>
                        </xsl:otherwise>
                    </xsl:choose>
                </cell>
            </xsl:for-each>
        </row>
    </xsl:template>
    
    <xsl:template match="tei:image">
        <xsl:element name="graphic" namespace="http://www.tei-c.org/ns/1.0">
            <xsl:attribute name="url" select="@url"/>
            <xsl:attribute name="n" select="@caption"/>
        </xsl:element>
    </xsl:template>
    
</xsl:stylesheet>