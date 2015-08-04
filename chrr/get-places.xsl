<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" exclude-result-prefixes="xsl xs xs nomisma rdf skos"
	xmlns:xd="http://www.oxygenxml.com/ns/doc/xsl" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:nuds="http://nomisma.org/nuds" xmlns:nomisma="http://nomisma.org/id/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:skos="http://www.w3.org/2008/05/skos#" xmlns:exsl="http://exslt.org/common" version="2.0">
	<xsl:output method="text"/>
	<xsl:variable name="geonames-url">
		<xsl:text>http://api.geonames.org</xsl:text>
	</xsl:variable>
	<xsl:variable name="countries">
		<countries>
			<xsl:for-each select="distinct-values(//findspot/COUNTRY)">
				<country id="{document(concat($geonames-url, '/search?q=', ., '&amp;username=anscoins&amp;style=full'))/geonames/geoname[1]/geonameId}">
					<xsl:value-of select="."/>
				</country>
			</xsl:for-each>
		</countries>
	</xsl:variable>

	<xsl:template match="/">
		<xsl:text>"id","name","nameuri","country","countryuri"</xsl:text>
		<xsl:text>
</xsl:text>
		<xsl:apply-templates select="descendant::findspot"/>
	</xsl:template>

	<xsl:template match="findspot">
		<xsl:variable name="findspot-id" select="document(concat($geonames-url, '/search?q=', NAME, '%20', COUNTRY, '&amp;username=anscoins&amp;style=full'))/geonames/geoname[1]/geonameId"/>
		<xsl:variable name="country" select="COUNTRY"/>
		<xsl:text>"</xsl:text>
		<xsl:value-of select="upper-case(findcode)"/>
		<xsl:text>",</xsl:text>
		<xsl:text>"</xsl:text>
		<xsl:value-of select="NAME"/>
		<xsl:text>","http://www.geonames.org/</xsl:text>
		<xsl:value-of select="$findspot-id"/>
		<xsl:text>/","</xsl:text>
		<xsl:value-of select="COUNTRY"/>
		<xsl:text>","http://www.geonames.org/</xsl:text>
		<xsl:value-of select="exsl:node-set($countries)//country[. = $country]/@id"/>
		<xsl:text>/"</xsl:text>
		<xsl:text>
</xsl:text>
	</xsl:template>
</xsl:stylesheet>
