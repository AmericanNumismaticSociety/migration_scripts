<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" 
    exclude-result-prefixes="#all" version="2.0">
    
    <xsl:strip-space elements="*"/>
    
    <xsl:output encoding="UTF-8" method="xml" indent="yes"/>

    <xsl:template match="@*|*|processing-instruction()|comment()">
        <xsl:copy>
            <xsl:apply-templates select="*|@*|text()|processing-instruction()|comment()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="folder[@name='Lorber']">
        <folder name="Lorber">
            <xsl:apply-templates select="file" mode="lorber"/>
        </folder>
    </xsl:template>
    
    <xsl:template match="file" mode="lorber">
        <file>
            <xsl:attribute name="name">
                <xsl:analyze-string select="@name"
                    regex="monogram\.lorber\.([0-9]+)(_?[1-9]?\.svg)">
                    
                    <xsl:matching-substring>
                        <xsl:variable name="num" select="number(regex-group(1))"/>
                        
                        <xsl:choose>
                            <xsl:when test="$num &gt;= 68">
                                <xsl:variable name="newNum" select="$num - 10"/>
                                <xsl:value-of select="concat('monogram.lorber.', $newNum, regex-group(2))"/>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:value-of select="concat('monogram.lorber.', regex-group(1), regex-group(2))"/>
                            </xsl:otherwise>
                        </xsl:choose>
                    </xsl:matching-substring>
                    
                    <xsl:non-matching-substring>
                        <xsl:value-of select="'test'"/>
                    </xsl:non-matching-substring>
                    
                </xsl:analyze-string>
                
            </xsl:attribute>
            <xsl:attribute name="editor">pvalfen</xsl:attribute>
            <xsl:attribute name="letters" select="@letters"/>
        </file>
    </xsl:template>


</xsl:stylesheet>
