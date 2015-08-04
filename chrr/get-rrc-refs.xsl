<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" exclude-result-prefixes="xs" version="2.0">
	<xsl:output method="text"/>
	
	<xsl:template match="/">
		<xsl:apply-templates select="descendant::cointype[coinrefs/CAT='rrc']"/>
	</xsl:template>
	
	<xsl:template match="cointype">
		<xsl:for-each select="coinrefs">
			<xsl:text>"</xsl:text>
			<xsl:choose>
				<xsl:when test="CAT='rrc'">
					<xsl:value-of select="concat('http://nomisma.org/id/rrc-', replace(REF, '/', '.'))"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="CAT"/>
					<xsl:text> </xsl:text>
					<xsl:value-of select="REF"/>
				</xsl:otherwise>
			</xsl:choose>
			<xsl:text>"</xsl:text>
			<xsl:if test="not(position()=last())">
				<xsl:text>,</xsl:text>
			</xsl:if>
		</xsl:for-each>
		<xsl:text>
</xsl:text>
	</xsl:template>
</xsl:stylesheet>
