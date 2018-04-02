<?xml version="1.0" encoding="UTF-8"?>

<!-- Author: Ethan Gruber
	Date: April 2018
	Function: Read the entity lookup list and incorporate entity URIs into TEI
	-->


<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:tei="http://www.tei-c.org/ns/1.0" xmlns:xlink="http://www.w3.org/1999/xlink"
	xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:openSearch="http://a9.com/-/spec/opensearchrss/1.0/" xmlns:eac="urn:isbn:1-931666-33-4"
	xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended" xmlns:skos="http://www.w3.org/2004/02/skos/core#"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:schema="http://schema.org/" version="2.0"
	exclude-result-prefixes="#all">

	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>
	<xsl:strip-space elements="*"/>


	<xsl:variable name="id" select="/tei:TEI/@xml:id"/>

	<!-- load lookup tree into node -->
	<xsl:variable name="lookup" as="node()*">
		<xsl:copy-of select="document('lookups.xml')//*[local-name() = $id]"/>
	</xsl:variable>

	<xsl:template match="@* | * | comment()">
		<xsl:copy>
			<xsl:apply-templates select="* | @* | text() | processing-instruction() | comment()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="tei:revisionDesc">
		<xsl:element name="revisionDesc" namespace="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates/>
			<change xmlns="http://www.tei-c.org/ns/1.0" when="{format-date(current-date(), '[Y]-[M01]-[D01]')}">
				<xsl:text>Included normalized entity labels and URIs from
				previous OpenRefine process.</xsl:text>
			</change>
		</xsl:element>
	</xsl:template>

	<xsl:template match="tei:profileDesc">
		<profileDesc xmlns="http://www.tei-c.org/ns/1.0">
			<xsl:apply-templates select="tei:langUsage | tei:textClass"/>

			<!-- evaluate whether there are URIs for personal, corporate, or place names -->
			<xsl:if test="$lookup//name[@type = 'person'][@uri] or $lookup//name[@type = 'org'][@uri]">
				<particDesc>
					<xsl:if test="$lookup//name[@type = 'person'][@uri]">
						<listPerson>
							<xsl:apply-templates select="$lookup//name[@type = 'person'][@uri]" mode="entity-list"/>
						</listPerson>
					</xsl:if>
					<xsl:if test="$lookup//name[@type = 'org'][@uri] or $lookup//name[@type = 'dynasty'][@uri] or $lookup//name[@type = 'ethnic'][@uri]">
						<listOrg>
							<xsl:apply-templates select="$lookup//name[@type = 'org'][@uri]|$lookup//name[@type = 'dynasty'][@uri]|$lookup//name[@type = 'ethnic'][@uri]" mode="entity-list"/>
						</listOrg>
					</xsl:if>
				</particDesc>
			</xsl:if>
			<xsl:if test="$lookup//name[@type = 'place'][@uri]">
				<settingDesc>
					<listPlace>
						<xsl:apply-templates select="$lookup//name[@type = 'place'][@uri]" mode="entity-list"/>
					</listPlace>
				</settingDesc>
			</xsl:if>
		</profileDesc>
	</xsl:template>

	<xsl:template match="name" mode="entity-list">
		<xsl:if test="not(@uri = preceding-sibling::name/@uri)">
			<xsl:variable name="id" select="tokenize(@uri, '/')[last()]"/>
			<xsl:variable name="authority">
				<xsl:choose>
					<xsl:when test="contains(@uri, 'nomisma.org')">nomisma</xsl:when>
					<xsl:when test="contains(@uri, 'viaf.org')">viaf</xsl:when>
					<xsl:when test="contains(@uri, 'numismatics.org')">numismatics</xsl:when>
					<xsl:when test="contains(@uri, 'geonames.org')">geonames</xsl:when>
					<xsl:when test="contains(@uri, 'wikidata.org')">wikidata</xsl:when>
				</xsl:choose>
			</xsl:variable>
			<xsl:variable name="element">
				<xsl:choose>
					<xsl:when test="@type = 'dynasty' or @type = 'ethnic'">
						<xsl:text>org</xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="@type"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:variable>
			
			<xsl:element name="{$element}" namespace="http://www.tei-c.org/ns/1.0">
				<xsl:attribute name="xml:id" select="concat($authority, '_', $id)"/>
				<xsl:choose>
					<xsl:when test="@type = 'person'">
						<xsl:element name="persName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:value-of select="@label"/>
						</xsl:element>
					</xsl:when>
					<xsl:when test="@type = 'place'">
						<xsl:element name="placeName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:value-of select="@label"/>
						</xsl:element>
					</xsl:when>
					<xsl:when test="@type = 'org'">
						<xsl:element name="orgName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:value-of select="@label"/>
						</xsl:element>
					</xsl:when>
				</xsl:choose>
				<xsl:element name="idno" namespace="http://www.tei-c.org/ns/1.0">
					<xsl:attribute name="type">URI</xsl:attribute>
					<xsl:value-of select="@uri"/>
				</xsl:element>
			</xsl:element>
		</xsl:if>
	</xsl:template>



	<!-- insert ID to authority URI, when applicable and change the element name -->
	<xsl:template match="tei:name[@type][ancestor::tei:body]">
		<xsl:variable name="key" select="normalize-space(.)"/>
		
		<xsl:choose>
			<xsl:when test="$lookup//name = $key">
				<xsl:variable name="type" select="$lookup//name[. = $key][1]/@type"/>
				
				<xsl:choose>
					<xsl:when test="$type = 'person'">
						<xsl:element name="persName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:if test="$lookup//name[. = $key]/@uri">
								<xsl:call-template name="corresp">
									<xsl:with-param name="uri" select="$lookup//name[. = $key][1]/@uri"/>
								</xsl:call-template>
							</xsl:if>
							<xsl:value-of select="$key"/>
						</xsl:element>
					</xsl:when>
					<xsl:when test="$type = 'org' or $type = 'dynasty' or $type = 'ethnic'">
						<xsl:element name="orgName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:if test="$lookup//name[. = $key]/@uri">
								<xsl:call-template name="corresp">
									<xsl:with-param name="uri" select="$lookup//name[. = $key][1]/@uri"/>
								</xsl:call-template>
							</xsl:if>
							<xsl:if test="$type = 'dynasty' or $type = 'ethnic'">
								<xsl:attribute name="type" select="$type"/>
							</xsl:if>
							<xsl:value-of select="$key"/>
						</xsl:element>
					</xsl:when>
					<xsl:when test="$type = 'place'">
						<xsl:element name="placeName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:if test="$lookup//name[. = $key]/@uri">
								<xsl:call-template name="corresp">
									<xsl:with-param name="uri" select="$lookup//name[. = $key][1]/@uri"/>
								</xsl:call-template>								
							</xsl:if>
							<xsl:value-of select="$key"/>
						</xsl:element>
					</xsl:when>
					<xsl:otherwise>
						<xsl:choose>
							<xsl:when test="$type = 'null'">
								<xsl:value-of select="$key"/>
							</xsl:when>
							<xsl:otherwise>
								<xsl:choose>
									<xsl:when test="$lookup//name[. = $key]/@uri">
										<xsl:element name="ref" namespace="http://www.tei-c.org/ns/1.0">
											<xsl:attribute name="target" select="$lookup//name[. = $key][1]/@uri"/>
											<xsl:value-of select="$key"/>
										</xsl:element>
									</xsl:when>
									<xsl:otherwise>
										<xsl:copy-of select="self::node()"/>
									</xsl:otherwise>
								</xsl:choose>
							</xsl:otherwise>
						</xsl:choose>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:when>
			<xsl:otherwise>
				<!-- if they key isn't found in the lookup table, then simply transform the element name, if applicable -->
				<xsl:choose>
					<xsl:when test="@type='pname'">
						<xsl:element name="persName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:apply-templates select="@*[not(name()='type')]|node()"/>
						</xsl:element>
					</xsl:when>
					<xsl:when test="@type='place'">
						<xsl:element name="placeName" namespace="http://www.tei-c.org/ns/1.0">
							<xsl:apply-templates select="@*[not(name()='type')]|node()"/>
						</xsl:element>
					</xsl:when>
					<xsl:otherwise>
						<xsl:copy-of select="self::node()"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>
	
	<xsl:template name="corresp">
		<xsl:param name="uri"/>
		<xsl:variable name="id" select="tokenize($uri, '/')[last()]"/>
		
		<xsl:variable name="authority">
			<xsl:choose>
				<xsl:when test="contains($uri, 'nomisma.org')">nomisma</xsl:when>
				<xsl:when test="contains($uri, 'viaf.org')">viaf</xsl:when>
				<xsl:when test="contains($uri, 'numismatics.org')">numismatics</xsl:when>
				<xsl:when test="contains($uri, 'geonames.org')">geonames</xsl:when>
				<xsl:when test="contains($uri, 'wikidata.org')">wikidata</xsl:when>
			</xsl:choose>
		</xsl:variable>
		
		<xsl:attribute name="corresp" select="concat('#', $authority, '_', $id)"/>
	</xsl:template>


</xsl:stylesheet>
