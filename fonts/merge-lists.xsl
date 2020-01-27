<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema"     exclude-result-prefixes="xs"
    version="2.0">
    
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>
    
    <xsl:variable name="monograms" as="node()*">
        <xsl:copy-of select="document('xforms/xml/monograms.xml')"/>
    </xsl:variable>
    
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="file">
        <xsl:variable name="id" select="@name"/>
        
        <file>
            <xsl:choose>
                <xsl:when test="$monograms//file[@name=$id]">
                    <xsl:attribute name="name" select="$monograms//file[@name=$id]/@name"/>
                    <xsl:attribute name="editor" select="$monograms//file[@name=$id]/@editor"/>
                    <xsl:attribute name="letters" select="$monograms//file[@name=$id]/@letters"/>                    
                </xsl:when>
                <xsl:otherwise>
                    <xsl:attribute name="name" select="@name"/>
                    <xsl:attribute name="editor"/>
                    <xsl:attribute name="letters"/>
                </xsl:otherwise>
            </xsl:choose>
        </file>
    </xsl:template>
    
</xsl:stylesheet>