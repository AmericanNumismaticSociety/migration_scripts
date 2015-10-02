<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:res="http://www.w3.org/2005/sparql-results#" version="2.0">
	<xsl:output method="text" encoding="UTF-8"/>


	<xsl:variable name="model" as="node()*">
		<xsl:copy-of select="/*"/>
	</xsl:variable>

	<xsl:variable name="objects" as="node()*">
		<objects>
			<xsl:for-each select="distinct-values(descendant::res:binding[@name='s']/res:uri)">
				<object>
					<xsl:value-of select="."/>
				</object>
			</xsl:for-each>
		</objects>
	</xsl:variable>

	<xsl:template match="/">
		<!-- headings -->
		<xsl:text>"SC Num","material","authority","date","mint","obverse type","obverse legend","reverse type","reverse legend"&#xd;</xsl:text>
		
		<xsl:for-each select="$objects//object">
			<xsl:variable name="uri" select="."/>
		
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="substring-before(substring-after(distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='text']/res:literal)[1], '2002 '), ' ::')"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='material']/res:literal)"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='authority']/res:literal)"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='date']/res:literal)"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="string-join(distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='mint']/res:literal), '|')"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='obvType']/res:literal)"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='obvLegend']/res:literal)"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='revType']/res:literal)"/>
			<xsl:text>",</xsl:text>
			<xsl:text>"</xsl:text>
			<xsl:value-of
				select="distinct-values($model//res:result[res:binding[@name='s']/res:uri = $uri]/res:binding[@name='revLegend']/res:literal)"/>
			<xsl:text>"</xsl:text>
			<xsl:text>&#xd;</xsl:text>
		</xsl:for-each>
	</xsl:template>

</xsl:stylesheet>
