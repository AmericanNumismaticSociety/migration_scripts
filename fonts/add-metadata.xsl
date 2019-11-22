<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:cc="http://creativecommons.org/ns#"
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:svg="http://www.w3.org/2000/svg"
    exclude-result-prefixes="xs"
    version="2.0">
    
    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>
    
    
    <xsl:variable name="uri-space">http://numismatics.org/symbolimages/</xsl:variable>
    <xsl:variable name="filePieces" select="tokenize(base-uri(), '/')"/>
    
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>
    
    <xsl:template match="cc:Work">
        <cc:Work rdf:about="{concat($uri-space, $filePieces[last() - 1], '/', $filePieces[last()])}">
            <xsl:apply-templates/>
            <cc:license rdf:resource="https://creativecommons.org/choose/mark/"/>
            
            <xsl:if test="not($filePieces[last() - 1] = 'ocre')">
                <dc:creator rdf:resource="https://orcid.org/0000-0001-7542-4252"/>
            </xsl:if>
        </cc:Work>
    </xsl:template>
    
</xsl:stylesheet>