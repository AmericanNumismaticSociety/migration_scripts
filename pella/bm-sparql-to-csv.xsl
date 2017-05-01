<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:res='http://www.w3.org/2005/sparql-results#' 
	version="2.0">
	<xsl:output encoding="UTF-8" method="text"/>
	
	<xsl:template match="/">
		<xsl:text>"uri","regno","ref",&#xa;</xsl:text>
		<xsl:apply-templates select="descendant::res:result[contains(res:binding[@name='s']/res:uri, 'CGR')]"/>
	</xsl:template>
	
	<xsl:template match="res:result">
		<xsl:text>"</xsl:text>
		<xsl:value-of select="res:binding[@name='s']/res:uri"/>
		<xsl:text>","</xsl:text>
		<xsl:value-of select="res:binding[@name='regno']/res:literal"/>
		<xsl:text>","</xsl:text>
		<xsl:value-of select="res:binding[@name='ref']/res:literal"/>
		<xsl:text>"&#xa;</xsl:text>
	</xsl:template>
	
</xsl:stylesheet>