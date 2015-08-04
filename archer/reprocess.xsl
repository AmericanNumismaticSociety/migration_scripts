<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:ead="urn:isbn:1-931666-22-9"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="2.0">
	<xsl:output method="xml" encoding="UTF-8" indent="yes"/>

	<xsl:variable name="all" as="element()*">
		<xsl:copy-of select="document('all-names.xml')/*"/>
	</xsl:variable>

	<xsl:variable name="creators" as="element()*">
		<xsl:copy-of select="document('distinct-creators.xml')/*"/>
	</xsl:variable>

	<xsl:variable name="names" as="element()*">
		<xsl:copy-of select="document('distinct-names.xml')/*"/>
	</xsl:variable>

	<xsl:template match="@*|node()">
		<xsl:copy>
			<xsl:apply-templates select="@*|node()"/>
		</xsl:copy>
	</xsl:template>

	<xsl:template match="ead:origination">
		<xsl:variable name="id" select="ancestor::ead:ead/ead:eadheader/ead:eadid"/>
		<xsl:variable name="entry" as="element()*">
			<xsl:copy-of select="$creators//entry[guides[contains(., $id)]]"/>
		</xsl:variable>

		<xsl:element name="origination" namespace="urn:isbn:1-931666-22-9">
			<xsl:element name="{if($entry/entityType='personal') then 'persname' else 'corpname'}" namespace="urn:isbn:1-931666-22-9">
				<xsl:attribute name="authfilenumber" select="concat('http://numismatics.org/authority/', $entry/id)"/>
				<xsl:attribute name="role">xeac:entity</xsl:attribute>
				<xsl:value-of select="$entry/preferredForm"/>
			</xsl:element>
		</xsl:element>
	</xsl:template>


	<xsl:template match="ead:persname|ead:corpname">
		<xsl:variable name="value" select="normalize-space(.)"/>

		<!-- look for the value in the distinct-names variable -->
		<xsl:variable name="entry" as="element()*">
			<xsl:copy-of select="$names//entry[value=$value]"/>
		</xsl:variable>

		<xsl:choose>
			<xsl:when test="string($entry/should_be)">
				<xsl:variable name="should_be" select="$entry/should_be"/>
				<xsl:element name="{local-name()}" namespace="urn:isbn:1-931666-22-9">
					<xsl:variable name="label" select="$names//entry[id=$should_be]/value"/>
					<xsl:variable name="uri" select="$names//entry[id=$should_be]/uri"/>
					<!-- add attributes if applicable -->
					<xsl:choose>
						<xsl:when test="$creators//entry[preferredForm=$label]">
							<xsl:attribute name="authfilenumber" select="concat('http://numismatics.org/authority/', $creators//entry[preferredForm=$label]/id)"/>
							<xsl:attribute name="role">xeac:entity</xsl:attribute>
						</xsl:when>
						<xsl:when test="string($uri)">
							<xsl:attribute name="authfilenumber">
								<xsl:choose>
									<xsl:when test="contains($uri, 'viaf')">
										<xsl:value-of select="tokenize($uri, '/')[5]"/>
									</xsl:when>
									<xsl:when test="contains($uri, 'geonames')">
										<xsl:value-of select="tokenize($uri, '/')[4]"/>
									</xsl:when>
								</xsl:choose>
							</xsl:attribute>
							<xsl:attribute name="source" select="if(contains($uri, 'viaf.org')) then 'viaf' else 'geonames'"/>
						</xsl:when>
					</xsl:choose>

					<xsl:value-of select="$label"/>
				</xsl:element>
			</xsl:when>
			<xsl:otherwise>
				<xsl:element name="{local-name()}" namespace="urn:isbn:1-931666-22-9">
					<xsl:variable name="label" select="$entry/value"/>
					<xsl:variable name="uri" select="$entry/uri"/>
					<xsl:choose>
						<xsl:when test="$creators//entry[preferredForm=$label]">
							<xsl:attribute name="authfilenumber" select="concat('http://numismatics.org/authority/', $creators//entry[preferredForm=$label]/id)"/>
							<xsl:attribute name="role">xeac:entity</xsl:attribute>
						</xsl:when>
						<xsl:when test="string($uri)">
							<xsl:attribute name="authfilenumber">
								<xsl:choose>
									<xsl:when test="contains($uri, 'viaf')">
										<xsl:value-of select="tokenize($uri, '/')[5]"/>
									</xsl:when>
									<xsl:when test="contains($uri, 'geonames')">
										<xsl:value-of select="tokenize($uri, '/')[4]"/>
									</xsl:when>
								</xsl:choose>
							</xsl:attribute>
							<xsl:attribute name="source" select="if(contains($uri, 'viaf.org')) then 'viaf' else 'geonames'"/>
						</xsl:when>
					</xsl:choose>

					<xsl:value-of select="$label"/>
				</xsl:element>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>

	<xsl:template match="mods:subject">
		<xsl:for-each select="mods:topic">
			<xsl:variable name="value" select="normalize-space(.)"/>
			<xsl:variable name="type" select="$all//entry[value=$value][1]/type"/>
			<xsl:variable name="element">
				<xsl:choose>
					<xsl:when test="$type='personal' or $type='corporate'">name</xsl:when>
					<xsl:when test="$type='subject'">topic</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="$type"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:variable>
			<xsl:variable name="type-attr">
				<xsl:choose>
					<xsl:when test="$type='personal'">personal</xsl:when>
					<xsl:when test="$type='corporate'">corporate</xsl:when>
				</xsl:choose>
			</xsl:variable>

			<!-- get preferredForm -->
			<xsl:variable name="entry" as="element()*">
				<xsl:copy-of select="$names//entry[value=$value]"/>
			</xsl:variable>

			<xsl:choose>
				<xsl:when test="string($entry/should_be)">
					<xsl:variable name="should_be" select="$entry/should_be"/>
					<xsl:element name="subject" namespace="http://www.loc.gov/mods/v3">
						<xsl:element name="{$element}" namespace="http://www.loc.gov/mods/v3">
							<xsl:variable name="label" select="$names//entry[id=$should_be]/value"/>
							<xsl:variable name="uri" select="$names//entry[id=$should_be]/uri"/>
							<!-- add attributes if applicable -->
							<xsl:choose>
								<xsl:when test="$creators//entry[preferredForm=$label]">
									<xsl:attribute name="valueURI" select="concat('http://numismatics.org/authority/', $creators//entry[preferredForm=$label]/id)"/>
								</xsl:when>
								<xsl:when test="string($uri)">
									<xsl:attribute name="valueURI" select="$uri"/>
								</xsl:when>
							</xsl:choose>

							<xsl:choose>
								<xsl:when test="string($type-attr)">
									<xsl:attribute name="type" select="$type-attr"/>
									<xsl:element name="namePart" namespace="http://www.loc.gov/mods/v3">
										<xsl:value-of select="$label"/>
									</xsl:element>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="$label"/>
								</xsl:otherwise>
							</xsl:choose>

						</xsl:element>
					</xsl:element>
				</xsl:when>
				<xsl:otherwise>
					<xsl:element name="subject" namespace="http://www.loc.gov/mods/v3">
						<xsl:element name="{$element}" namespace="http://www.loc.gov/mods/v3">
							<xsl:variable name="label" select="$entry/value"/>
							<xsl:variable name="uri" select="$entry/uri"/>
							<xsl:choose>
								<xsl:when test="$creators//entry[preferredForm=$label]">
									<xsl:attribute name="valueURI" select="concat('http://numismatics.org/authority/', $creators//entry[preferredForm=$label]/id)"/>
								</xsl:when>
								<xsl:when test="string($uri)">
									<xsl:attribute name="valueURI" select="$uri"/>
								</xsl:when>
							</xsl:choose>

							<xsl:choose>
								<xsl:when test="string($type-attr)">
									<xsl:attribute name="type" select="$type-attr"/>
									<xsl:element name="namePart" namespace="http://www.loc.gov/mods/v3">
										<xsl:value-of select="$label"/>
									</xsl:element>
								</xsl:when>
								<xsl:otherwise>
									<xsl:value-of select="$label"/>
								</xsl:otherwise>
							</xsl:choose>
						</xsl:element>
					</xsl:element>
				</xsl:otherwise>
			</xsl:choose>
		</xsl:for-each>
	</xsl:template>

	<xsl:template match="mods:modsCollection">
		<xsl:element name="{local-name()}" namespace="http://www.loc.gov/mods/v3">
			<xsl:attribute name="xsi:schemaLocation">
				<xsl:text>http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/mods.xsd</xsl:text>
			</xsl:attribute>
			<xsl:apply-templates/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="mods:mods">
		<xsl:element name="{local-name()}" namespace="http://www.loc.gov/mods/v3">
			<xsl:attribute name="version">3.5</xsl:attribute>
			<xsl:apply-templates/>
		</xsl:element>
	</xsl:template>
	
	<xsl:template match="mods:form[contains(., 'Photograph')]">
		<xsl:element name="form" namespace="http://www.loc.gov/mods/v3">
			<xsl:attribute name="valueURI">http://vocab.getty.edu/aat/300046300</xsl:attribute>
			<xsl:text>Photographs</xsl:text>
		</xsl:element>
	</xsl:template>

</xsl:stylesheet>
