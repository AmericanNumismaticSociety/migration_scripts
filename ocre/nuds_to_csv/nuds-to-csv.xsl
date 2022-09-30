<?xml version="1.0" encoding="UTF-8"?>
<!-- Author: Ethan Gruber
    Date: September 2022
    Function: Transform the current version of the OCRE NUDS into a CSV file that can be cleaned up in OpenRefine prior to revision into new version -->

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:nuds="http://nomisma.org/nuds"
    xmlns:numishare="https://github.com/ewg118/numishare" xmlns:xlink="http://www.w3.org/1999/xlink" exclude-result-prefixes="#all" version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output method="text"/>

    <xsl:template match="/">
        <!-- first, get distinct type descriptions -->
        <xsl:variable name="all-types" as="item()">
            <types>
                <xsl:for-each
                    select="collection(iri-to-uri('file:///home/komet/ans_migration/ocre/nuds_to_csv/old_nuds?select=ric.10*.xml'))//nuds:type/nuds:description">
                    <xsl:variable name="type" select="normalize-space(.)"/>

                    <type>
                        <xsl:attribute name="sortId">
                            <xsl:value-of select="numishare:getSortId(ancestor::nuds:nuds/nuds:control/nuds:recordId)"/>
                        </xsl:attribute>

                        <xsl:attribute name="side">
                            <xsl:choose>
                                <xsl:when test="ancestor::nuds:obverse">obverse</xsl:when>
                                <xsl:when test="ancestor::nuds:reverse">reverse</xsl:when>
                            </xsl:choose>
                        </xsl:attribute>

                        <xsl:value-of select="$type"/>
                    </type>
                </xsl:for-each>
            </types>
        </xsl:variable>


        <xsl:variable name="distinct-types" as="item()">
            <types>
                <xsl:for-each select="$all-types//type[@side = 'obverse']">
                    <xsl:sort select="@sortId"/>

                    <xsl:if test="not(. = preceding-sibling::type)">
                        <type>
                            <xsl:attribute name="code" select="concat('O', format-number(position(), '00000'))"/>
                            <xsl:value-of select="."/>
                        </type>
                    </xsl:if>
                </xsl:for-each>
                <xsl:for-each select="$all-types//type[@side = 'reverse']">
                    <xsl:sort select="@sortId"/>

                    <xsl:if test="not(. = preceding-sibling::type)">
                        <type>
                            <xsl:attribute name="code" select="concat('R', format-number(position(), '00000'))"/>
                            <xsl:value-of select="."/>
                        </type>
                    </xsl:if>
                </xsl:for-each>
            </types>
        </xsl:variable>
        
        <xsl:variable name="types" as="item()">
            <types>
                <xsl:for-each select="$distinct-types//type[starts-with(@code, 'O')]">
                    <type>
                        <xsl:attribute name="code" select="concat('O', format-number(position(), '0000'))"/>
                        <xsl:value-of select="."/>
                    </type>
                </xsl:for-each>
                <xsl:for-each select="$distinct-types//type[starts-with(@code, 'R')]">
                    <type>
                        <xsl:attribute name="code" select="concat('R', format-number(position(), '0000'))"/>
                        <xsl:value-of select="."/>
                    </type>
                </xsl:for-each>
            </types>
        </xsl:variable>

        <xsl:variable name="row" as="item()">
            <sheet>
                <xsl:for-each select="collection(iri-to-uri('file:///home/komet/ans_migration/ocre/nuds_to_csv/old_nuds?select=ric.10*.xml'))">
                    <row>
                        <xsl:apply-templates select="/nuds:nuds">
                            <xsl:with-param name="types" select="$types"/>
                        </xsl:apply-templates>
                    </row>
                </xsl:for-each>
            </sheet>
        </xsl:variable>

        <!-- output the types -->
        <!--<xsl:text>code,en&#x0A;</xsl:text>
        <xsl:for-each select="$types//type">

            <xsl:value-of select="@code"/>
            <xsl:text>,"</xsl:text>
            <xsl:value-of select="replace(., '&#x022;', '&#x022;&#x022;')"/>
            <xsl:text>"&#x0A;</xsl:text>
        </xsl:for-each>-->
        
        <!-- output the spreadsheet -->

        <!-- headings -->
        <xsl:for-each select="$row//row[1]">
            <xsl:for-each select="child::*">
                <xsl:value-of select="name()"/>       
                <xsl:if test="not(position() = last())">
                    <xsl:text>,</xsl:text>
                </xsl:if>
            </xsl:for-each>
            <xsl:text>&#x0A;</xsl:text>
        </xsl:for-each>
        
        <!-- rows -->
        <xsl:for-each select="$row//row">
            <xsl:sort select="sortId"/>
            <xsl:for-each select="child::*">
                <xsl:text>"</xsl:text>
                <xsl:value-of select="replace(., '&#x022;', '&#x022;&#x022;')"/>      
                <xsl:text>"</xsl:text>
                <xsl:if test="not(position() = last())">
                    <xsl:text>,</xsl:text>
                </xsl:if>
            </xsl:for-each>
            <xsl:text>&#x0A;</xsl:text>
        </xsl:for-each>


    </xsl:template>

    <xsl:template match="nuds:nuds">
        <xsl:param name="types"/>

        <id>
            <xsl:value-of select="nuds:control/nuds:recordId"/>
        </id>
        <sortId>
            <xsl:value-of select="numishare:getSortId(nuds:control/nuds:recordId)"/>
        </sortId>
        
        <parentId>
            <xsl:value-of select="string-join(nuds:control/nuds:otherRecordId[@semantic = 'skos:broader'], '|')"/>
        </parentId>
        
        <replacedBy>
            <xsl:value-of select="string-join(nuds:control/nuds:otherRecordId[@semantic = 'dcterms:isReplacedBy'], '|')"/>
        </replacedBy>
        
        
        
        <xsl:apply-templates select="descendant::nuds:typeDesc">
            <xsl:with-param name="types" select="$types"/>
        </xsl:apply-templates>
        
        
        
    </xsl:template>

    <xsl:template match="nuds:typeDesc">
        <xsl:param name="types"/>

        <xsl:choose>
            <xsl:when test="nuds:date">
                <fromDate>
                    <xsl:value-of select="number(nuds:date/@standardDate)"/>
                </fromDate>
                <toDate>
                    <xsl:value-of select="number(nuds:date/@standardDate)"/>
                </toDate>
            </xsl:when>
            <xsl:when test="nuds:dateRange">
                <fromDate>
                    <xsl:value-of select="number(nuds:dateRange/nuds:fromDate/@standardDate)"/>
                </fromDate>
                <toDate>
                    <xsl:value-of select="number(nuds:dateRange/nuds:toDate/@standardDate)"/>
                </toDate>
            </xsl:when>
            <xsl:otherwise>
                <fromDate/>
                <toDate/>
            </xsl:otherwise>
        </xsl:choose>

        <objectType>
            <xsl:choose>
                <xsl:when test="nuds:objectType/@xlink:href">
                    <xsl:value-of select="string-join(nuds:objectType/@xlink:href, '|')"/>
                </xsl:when>
                <xsl:when test="nuds:objectType[text()]">
                    <xsl:value-of select="normalize-space(nuds:objectType)"/>
                </xsl:when>
            </xsl:choose>
        </objectType>

        <manufacture>
            <xsl:choose>
                <xsl:when test="nuds:manufacture/@xlink:href">
                    <xsl:value-of select="nuds:manufacture/@xlink:href"/>
                </xsl:when>
                <xsl:when test="nuds:manufacture[text()]">
                    <xsl:value-of select="normalize-space(nuds:manufacture)"/>
                </xsl:when>
            </xsl:choose>
        </manufacture>

        <material>
            <xsl:choose>
                <xsl:when test="nuds:material/@xlink:href">
                    <xsl:value-of select="string-join(nuds:material/@xlink:href, '|')"/>
                </xsl:when>
                <xsl:when test="nuds:material[text()]">
                    <xsl:value-of select="normalize-space(nuds:material)"/>
                </xsl:when>
            </xsl:choose>
        </material>

        <denomination>
            <xsl:choose>
                <xsl:when test="nuds:denomination/@xlink:href">
                    <xsl:value-of select="string-join(nuds:denomination/@xlink:href, '|')"/>
                </xsl:when>
                <xsl:when test="nuds:denomination[text()]">
                    <xsl:value-of select="normalize-space(nuds:denomination)"/>
                </xsl:when>
            </xsl:choose>
        </denomination>

        <mint>
            <xsl:choose>
                <xsl:when test="nuds:geographic/nuds:geogname[@xlink:role = 'mint']/@xlink:href">
                    <xsl:value-of select="string-join(nuds:geographic/nuds:geogname[@xlink:role = 'mint']/@xlink:href, '|')"/>
                </xsl:when>
                <xsl:when test="nuds:geographic/nuds:geogname[@xlink:role = 'mint'][text()]">
                    <xsl:value-of select="string-join(nuds:geographic/nuds:geogname[@xlink:role = 'mint'], '|')"/>
                </xsl:when>
            </xsl:choose>
        </mint>
        <region>
            <xsl:choose>
                <xsl:when test="nuds:geographic/nuds:geogname[@xlink:role = 'region']/@xlink:href">
                    <xsl:value-of select="string-join(nuds:geographic/nuds:geogname[@xlink:role = 'region']/@xlink:href, '|')"/>
                </xsl:when>
                <xsl:when test="nuds:geographic/nuds:geogname[@xlink:role = 'region'][text()]">
                    <xsl:value-of select="string-join(nuds:geographic/nuds:geogname[@xlink:role = 'region'], '|')"/>
                </xsl:when>
            </xsl:choose>
        </region>
        <authority>
            <xsl:value-of select="string-join(nuds:authority/nuds:persname[@xlink:role = 'authority']/@xlink:href, '|')"/>
        </authority>
        
        <issuer>
            <xsl:value-of select="string-join(nuds:authority/nuds:persname[@xlink:role = 'issuer']/@xlink:href, '|')"/>
        </issuer>

        <obverse_type>
            <xsl:variable name="obverse_type" select="normalize-space(nuds:obverse/nuds:type/nuds:description)"/>

            <xsl:value-of select="$types//type[. = $obverse_type][1]/@code"/>
        </obverse_type>

        <obverse_legend>
            <xsl:value-of select="normalize-space(nuds:obverse/nuds:legend)"/>
        </obverse_legend>

        <obverse_portrait>
            <xsl:value-of select="string-join(nuds:obverse/nuds:persname[@xlink:role = 'portrait']/@xlink:href, '|')"/>
        </obverse_portrait>

        <obverse_deity>
            <xsl:value-of select="string-join(nuds:obverse/nuds:persname[@xlink:role = 'deity'], '|')"/>
        </obverse_deity>

        <obverse_symbol>
            <xsl:if test="nuds:obverse/nuds:symbol/@xlink:href">
                <xsl:value-of select="string-join(nuds:obverse/nuds:symbol/@xlink:href, '|')"/>
            </xsl:if>
            <xsl:if test="nuds:obverse/nuds:symbol/@xlink:href and nuds:obverse/nuds:symbol[not(@xlink:href)]">
                <xsl:text>|</xsl:text>
            </xsl:if>   
            <xsl:if test="nuds:obverse/nuds:symbol[not(@xlink:href)]">
                <xsl:value-of select="string-join(nuds:obverse/nuds:symbol[not(@xlink:href)], '|')"/>
            </xsl:if>
            
        </obverse_symbol>

        <reverse_type>
            <xsl:variable name="reverse_type" select="normalize-space(nuds:reverse/nuds:type/nuds:description)"/>

            <xsl:value-of select="$types//type[. = $reverse_type][1]/@code"/>
        </reverse_type>

        <reverse_legend>
            <xsl:value-of select="normalize-space(nuds:reverse/nuds:legend)"/>
        </reverse_legend>

        <reverse_portrait>
            <xsl:value-of select="string-join(nuds:reverse/nuds:persname[@xlink:role = 'portrait']/@xlink:href, '|')"/>
        </reverse_portrait>

        <reverse_deity>
            <xsl:value-of select="string-join(nuds:reverse/nuds:persname[@xlink:role = 'deity'], '|')"/>
        </reverse_deity>

        <reverse_symbol>
            <xsl:if test="nuds:reverse/nuds:symbol/@xlink:href">
                <xsl:value-of select="string-join(nuds:reverse/nuds:symbol/@xlink:href, '|')"/>
            </xsl:if>
            <xsl:if test="nuds:reverse/nuds:symbol/@xlink:href and nuds:reverse/nuds:symbol[not(@xlink:href)]">
                <xsl:text>|</xsl:text>
            </xsl:if>   
            <xsl:if test="nuds:reverse/nuds:symbol[not(@xlink:href)]">
                <xsl:value-of select="string-join(nuds:reverse/nuds:symbol[not(@xlink:href)], '|')"/>
            </xsl:if>
            
        </reverse_symbol>

    </xsl:template>


    <xsl:function name="numishare:getSortId">
        <xsl:param name="recordId"/>

        <xsl:variable name="segs" select="tokenize($recordId, '\.')"/>
        <xsl:variable name="auth">
            <xsl:choose>
                <xsl:when test="$segs[3] = 'aug'">001</xsl:when>
                <xsl:when test="$segs[3] = 'tib'">002</xsl:when>
                <xsl:when test="$segs[3] = 'gai'">003</xsl:when>
                <xsl:when test="$segs[3] = 'cl'">004</xsl:when>
                <xsl:when test="$segs[3] = 'ner'">
                    <xsl:choose>
                        <xsl:when test="$segs[2] = '1(2)'">005</xsl:when>
                        <xsl:when test="$segs[2] = '2'">015</xsl:when>
                    </xsl:choose>
                </xsl:when>
                <xsl:when test="$segs[3] = 'clm'">006</xsl:when>
                <xsl:when test="$segs[3] = 'cw'">007</xsl:when>
                <xsl:when test="$segs[3] = 'gal'">008</xsl:when>
                <xsl:when test="$segs[3] = 'ot'">009</xsl:when>
                <xsl:when test="$segs[3] = 'vit'">010</xsl:when>
                <xsl:when test="$segs[3] = 'ves'">011</xsl:when>
                <xsl:when test="$segs[3] = 'tit'">012</xsl:when>
                <xsl:when test="$segs[3] = 'dom'">013</xsl:when>
                <xsl:when test="$segs[3] = 'anys'">014</xsl:when>
                <xsl:when test="$segs[3] = 'tr'">016</xsl:when>
                <xsl:when test="$segs[3] = 'hdn'">017</xsl:when>
                <xsl:when test="$segs[3] = 'ant'">018</xsl:when>
                <xsl:when test="$segs[3] = 'm_aur'">019</xsl:when>
                <xsl:when test="$segs[3] = 'com'">020</xsl:when>
                <xsl:when test="$segs[3] = 'pert'">021</xsl:when>
                <xsl:when test="$segs[3] = 'dj'">022</xsl:when>
                <xsl:when test="$segs[3] = 'pn'">023</xsl:when>
                <xsl:when test="$segs[3] = 'ca'">024</xsl:when>
                <xsl:when test="$segs[3] = 'ss'">025</xsl:when>
                <xsl:when test="$segs[3] = 'crl'">026</xsl:when>
                <xsl:when test="$segs[3] = 'ge'">027</xsl:when>
                <xsl:when test="$segs[3] = 'mcs'">028</xsl:when>
                <xsl:when test="$segs[3] = 'el'">029</xsl:when>
                <xsl:when test="$segs[3] = 'sa'">030</xsl:when>
                <xsl:when test="$segs[3] = 'max_i'">031</xsl:when>
                <xsl:when test="$segs[3] = 'pa'">032</xsl:when>
                <xsl:when test="$segs[3] = 'mxs'">033</xsl:when>
                <xsl:when test="$segs[3] = 'gor_i'">034</xsl:when>
                <xsl:when test="$segs[3] = 'gor_ii'">035</xsl:when>
                <xsl:when test="$segs[3] = 'balb'">036</xsl:when>
                <xsl:when test="$segs[3] = 'pup'">037</xsl:when>
                <xsl:when test="$segs[3] = 'gor_iii_caes'">038</xsl:when>
                <xsl:when test="$segs[3] = 'gor_iii'">039</xsl:when>
                <xsl:when test="$segs[3] = 'ph_i'">040</xsl:when>
                <xsl:when test="$segs[3] = 'pac'">041</xsl:when>
                <xsl:when test="$segs[3] = 'jot'">042</xsl:when>
                <xsl:when test="$segs[3] = 'mar_s'">043</xsl:when>
                <xsl:when test="$segs[3] = 'spon'">044</xsl:when>
                <xsl:when test="$segs[3] = 'tr_d'">045</xsl:when>
                <xsl:when test="$segs[3] = 'tr_g'">046</xsl:when>
                <xsl:when test="$segs[3] = 'vo'">047</xsl:when>
                <xsl:when test="$segs[3] = 'aem'">048</xsl:when>
                <xsl:when test="$segs[3] = 'uran_ant'">049</xsl:when>
                <xsl:when test="$segs[3] = 'val_i'">050</xsl:when>
                <xsl:when test="$segs[3] = 'val_i-gall'">051</xsl:when>
                <xsl:when test="$segs[3] = 'val_i-gall-val_ii-sala'">052</xsl:when>
                <xsl:when test="$segs[3] = 'marin'">053</xsl:when>
                <xsl:when test="$segs[3] = 'gall(1)'">054</xsl:when>
                <xsl:when test="$segs[3] = 'gall_sala(1)'">055</xsl:when>
                <xsl:when test="$segs[3] = 'gall_sals'">056</xsl:when>
                <xsl:when test="$segs[3] = 'sala(1)'">057</xsl:when>
                <xsl:when test="$segs[3] = 'val_ii'">058</xsl:when>
                <xsl:when test="$segs[3] = 'sals'">059</xsl:when>
                <xsl:when test="$segs[3] = 'qjg'">060</xsl:when>
                <xsl:when test="$segs[3] = 'gall(2)'">061</xsl:when>
                <xsl:when test="$segs[3] = 'gall_sala(2)'">062</xsl:when>
                <xsl:when test="$segs[3] = 'sala(2)'">063</xsl:when>
                <xsl:when test="$segs[3] = 'cg'">064</xsl:when>
                <xsl:when test="$segs[3] = 'qu'">065</xsl:when>
                <xsl:when test="$segs[3] = 'aur'">066</xsl:when>
                <xsl:when test="$segs[3] = 'aur_seva'">067</xsl:when>
                <xsl:when test="$segs[3] = 'seva'">068</xsl:when>
                <xsl:when test="$segs[3] = 'tac'">069</xsl:when>
                <xsl:when test="$segs[3] = 'fl'">070</xsl:when>
                <xsl:when test="$segs[3] = 'intr'">071</xsl:when>
                <xsl:when test="$segs[3] = 'pro'">072_01</xsl:when>
                <xsl:when test="$segs[3] = 'car'">072_02</xsl:when>
                <xsl:when test="$segs[3] = 'dio'">072_03</xsl:when>
                <xsl:when test="$segs[3] = 'post'">072_04</xsl:when>
                <xsl:when test="$segs[3] = 'lae'">072_05</xsl:when>
                <xsl:when test="$segs[3] = 'mar'">072_06</xsl:when>
                <xsl:when test="$segs[3] = 'vict'">072_07</xsl:when>
                <xsl:when test="$segs[3] = 'tet_i'">072_08</xsl:when>
                <xsl:when test="$segs[3] = 'cara'">072_09</xsl:when>
                <xsl:when test="$segs[3] = 'cara-dio-max_her'">072_10</xsl:when>
                <xsl:when test="$segs[3] = 'all'">072_11</xsl:when>
                <xsl:when test="$segs[3] = 'mac_ii'">072_12</xsl:when>
                <xsl:when test="$segs[3] = 'quit'">072_13</xsl:when>
                <xsl:when test="$segs[3] = 'zen'">072_14</xsl:when>
                <xsl:when test="$segs[3] = 'vab'">072_15</xsl:when>
                <xsl:when test="$segs[3] = 'reg'">072_16</xsl:when>
                <xsl:when test="$segs[3] = 'dry'">072_17</xsl:when>
                <xsl:when test="$segs[3] = 'aurl'">072_18</xsl:when>
                <xsl:when test="$segs[3] = 'dom_g'">072_19</xsl:when>
                <xsl:when test="$segs[3] = 'sat'">072_20</xsl:when>
                <xsl:when test="$segs[3] = 'bon'">072_21</xsl:when>
                <xsl:when test="$segs[3] = 'jul_i'">072_22</xsl:when>
                <xsl:when test="$segs[3] = 'ama'">072_23</xsl:when>
                <xsl:when test="$segs[2] = '6'">
                    <xsl:choose>
                        <xsl:when test="$segs[3] = 'lon'">073</xsl:when>
                        <xsl:when test="$segs[3] = 'tri'">074</xsl:when>
                        <xsl:when test="$segs[3] = 'lug'">075</xsl:when>
                        <xsl:when test="$segs[3] = 'tic'">076</xsl:when>
                        <xsl:when test="$segs[3] = 'aq'">077</xsl:when>
                        <xsl:when test="$segs[3] = 'rom'">078</xsl:when>
                        <xsl:when test="$segs[3] = 'ost'">079</xsl:when>
                        <xsl:when test="$segs[3] = 'carth'">080</xsl:when>
                        <xsl:when test="$segs[3] = 'sis'">081</xsl:when>
                        <xsl:when test="$segs[3] = 'serd'">082</xsl:when>
                        <xsl:when test="$segs[3] = 'thes'">082a</xsl:when>
                        <xsl:when test="$segs[3] = 'her'">083</xsl:when>
                        <xsl:when test="$segs[3] = 'nic'">084</xsl:when>
                        <xsl:when test="$segs[3] = 'cyz'">085</xsl:when>
                        <xsl:when test="$segs[3] = 'anch'">086</xsl:when>
                        <xsl:when test="$segs[3] = 'alex'">087</xsl:when>
                    </xsl:choose>
                </xsl:when>
                <xsl:when test="$segs[2] = '7'">
                    <xsl:choose>
                        <xsl:when test="$segs[3] = 'lon'">088</xsl:when>
                        <xsl:when test="$segs[3] = 'lug'">089</xsl:when>
                        <xsl:when test="$segs[3] = 'tri'">090</xsl:when>
                        <xsl:when test="$segs[3] = 'ar'">091</xsl:when>
                        <xsl:when test="$segs[3] = 'rom'">092</xsl:when>
                        <xsl:when test="$segs[3] = 'tic'">093</xsl:when>
                        <xsl:when test="$segs[3] = 'aq'">094</xsl:when>
                        <xsl:when test="$segs[3] = 'sis'">095</xsl:when>
                        <xsl:when test="$segs[3] = 'sir'">096</xsl:when>
                        <xsl:when test="$segs[3] = 'serd'">096a</xsl:when>
                        <xsl:when test="$segs[3] = 'thes'">097</xsl:when>
                        <xsl:when test="$segs[3] = 'her'">098</xsl:when>
                        <xsl:when test="$segs[3] = 'cnp'">099</xsl:when>
                        <xsl:when test="$segs[3] = 'nic'">100</xsl:when>
                        <xsl:when test="$segs[3] = 'cyz'">101</xsl:when>
                        <xsl:when test="$segs[3] = 'anch'">102</xsl:when>
                        <xsl:when test="$segs[3] = 'alex'">103</xsl:when>
                    </xsl:choose>
                </xsl:when>
                <xsl:when test="$segs[2] = '8'">
                    <xsl:choose>
                        <xsl:when test="$segs[3] = 'amb'">104</xsl:when>
                        <xsl:when test="$segs[3] = 'tri'">105</xsl:when>
                        <xsl:when test="$segs[3] = 'lug'">106</xsl:when>
                        <xsl:when test="$segs[3] = 'ar'">107</xsl:when>
                        <xsl:when test="$segs[3] = 'med'">107a</xsl:when>
                        <xsl:when test="$segs[3] = 'rom'">108</xsl:when>
                        <xsl:when test="$segs[3] = 'aq'">109</xsl:when>
                        <xsl:when test="$segs[3] = 'sis'">110</xsl:when>
                        <xsl:when test="$segs[3] = 'sir'">111</xsl:when>
                        <xsl:when test="$segs[3] = 'thes'">112</xsl:when>
                        <xsl:when test="$segs[3] = 'her'">113</xsl:when>
                        <xsl:when test="$segs[3] = 'cnp'">114</xsl:when>
                        <xsl:when test="$segs[3] = 'nic'">115</xsl:when>
                        <xsl:when test="$segs[3] = 'cyz'">116</xsl:when>
                        <xsl:when test="$segs[3] = 'anch'">117</xsl:when>
                        <xsl:when test="$segs[3] = 'alex'">118_00</xsl:when>
                    </xsl:choose>
                </xsl:when>
                <xsl:when test="$segs[2] = '9'">
                    <xsl:choose>
                        <xsl:when test="$segs[3] = 'alex'">118_01</xsl:when>
                        <xsl:when test="$segs[3] = 'anch'">118_02</xsl:when>
                        <xsl:when test="$segs[3] = 'aq'">118_03</xsl:when>
                        <xsl:when test="$segs[3] = 'ar'">118_04</xsl:when>
                        <xsl:when test="$segs[3] = 'cnp'">118_05</xsl:when>
                        <xsl:when test="$segs[3] = 'cyz'">118_06</xsl:when>
                        <xsl:when test="$segs[3] = 'her'">118_07</xsl:when>
                        <xsl:when test="$segs[3] = 'lon'">118_08</xsl:when>
                        <xsl:when test="$segs[3] = 'lug'">118_09</xsl:when>
                        <xsl:when test="$segs[3] = 'med'">118_10</xsl:when>
                        <xsl:when test="$segs[3] = 'nic'">118_11</xsl:when>
                        <xsl:when test="$segs[3] = 'rom'">118_12</xsl:when>
                        <xsl:when test="$segs[3] = 'sir'">118_13</xsl:when>
                        <xsl:when test="$segs[3] = 'sis'">118_14</xsl:when>
                        <xsl:when test="$segs[3] = 'thes'">118_15</xsl:when>
                        <xsl:when test="$segs[3] = 'tri'">118_16</xsl:when>
                    </xsl:choose>
                </xsl:when>
                <xsl:when test="$segs[2] = '10'">
                    <xsl:choose>
                        <xsl:when test="$segs[3] = 'arc_e'">119</xsl:when>
                        <xsl:when test="$segs[3] = 'theo_ii_e'">120</xsl:when>
                        <xsl:when test="$segs[3] = 'marc_e'">121</xsl:when>
                        <xsl:when test="$segs[3] = 'leo_i_e'">122</xsl:when>
                        <xsl:when test="$segs[3] = 'leo_ii_e'">123</xsl:when>
                        <xsl:when test="$segs[3] = 'leo_ii-zen_e'">124</xsl:when>
                        <xsl:when test="$segs[3] = 'zeno(1)_e'">125</xsl:when>
                        <xsl:when test="$segs[3] = 'bas_e'">126</xsl:when>
                        <xsl:when test="$segs[3] = 'bas-mar_e'">127</xsl:when>
                        <xsl:when test="$segs[3] = 'zeno(2)_e'">128</xsl:when>
                        <xsl:when test="$segs[3] = 'leon_e'">129</xsl:when>
                        <xsl:when test="$segs[3] = 'hon_w'">130</xsl:when>
                        <xsl:when test="$segs[3] = 'pr_att_w'">131</xsl:when>
                        <xsl:when test="$segs[3] = 'con_iii_w'">132</xsl:when>
                        <xsl:when test="$segs[3] = 'max_barc_w'">133</xsl:when>
                        <xsl:when test="$segs[3] = 'jov_w'">134</xsl:when>
                        <xsl:when test="$segs[3] = 'theo_ii_w'">135</xsl:when>
                        <xsl:when test="$segs[3] = 'joh_w'">136</xsl:when>
                        <xsl:when test="$segs[3] = 'valt_iii_w'">137</xsl:when>
                        <xsl:when test="$segs[3] = 'pet_max_w'">138</xsl:when>
                        <xsl:when test="$segs[3] = 'marc_w'">139</xsl:when>
                        <xsl:when test="$segs[3] = 'av_w'">140</xsl:when>
                        <xsl:when test="$segs[3] = 'leo_i_w'">141</xsl:when>
                        <xsl:when test="$segs[3] = 'maj_w'">142</xsl:when>
                        <xsl:when test="$segs[3] = 'lib_sev_w'">143</xsl:when>
                        <xsl:when test="$segs[3] = 'anth_w'">144</xsl:when>
                        <xsl:when test="$segs[3] = 'oly_w'">145</xsl:when>
                        <xsl:when test="$segs[3] = 'glyc_w'">146</xsl:when>
                        <xsl:when test="$segs[3] = 'jul_nep_w'">147</xsl:when>
                        <xsl:when test="$segs[3] = 'bas_w'">148</xsl:when>
                        <xsl:when test="$segs[3] = 'rom_aug_w'">149</xsl:when>
                        <xsl:when test="$segs[3] = 'odo_w'">150</xsl:when>
                        <xsl:when test="$segs[3] = 'zeno_w'">151</xsl:when>
                        <xsl:when test="$segs[3] = 'visi'">152</xsl:when>
                        <xsl:when test="$segs[3] = 'gallia'">153</xsl:when>
                        <xsl:when test="$segs[3] = 'spa'">154</xsl:when>
                        <xsl:when test="$segs[3] = 'afr'">155</xsl:when>
                    </xsl:choose>
                </xsl:when>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="num">
            <xsl:analyze-string regex="([0-9]+)(.*)" select="$segs[4]">
                <xsl:matching-substring>
                    <xsl:value-of select="concat(format-number(number(regex-group(1)), '0000'), regex-group(2))"/>
                </xsl:matching-substring>
            </xsl:analyze-string>
        </xsl:variable>
        <xsl:value-of select="concat($auth, '.', $num)"/>


    </xsl:function>

</xsl:stylesheet>
