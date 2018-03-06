
RGraph=window.RGraph||{isRGraph:true};RGraph.SVG=RGraph.SVG||{};(function(win,doc,undefined)
{var RG=RGraph,ua=navigator.userAgent,ma=Math,win=window,doc=document;RG.SVG.Line=function(conf)
{this.set=function(name,value)
{if(arguments.length===1&&typeof name==='object'){for(i in arguments[0]){if(typeof i==='string'){var ret=RG.SVG.commonSetter({object:this,name:i,value:arguments[0][i]});name=ret.name;value=ret.value;this.set(name,value);}}}else{var ret=RG.SVG.commonSetter({object:this,name:name,value:value});name=ret.name;value=ret.value;this.properties[name]=value;if(name==='colors'){this.originalColors=RG.SVG.arrayClone(value);this.colorsParsed=false;}}
return this;};this.id=conf.id;this.uid=RG.SVG.createUID();this.container=document.getElementById(this.id);this.layers={};this.svg=RG.SVG.createSVG({object:this,container:this.container});this.isRGraph=true;this.width=Number(this.svg.getAttribute('width'));this.height=Number(this.svg.getAttribute('height'));if(RG.SVG.isArray(conf.data)&&RG.SVG.isArray(conf.data[0])){this.data=RG.SVG.arrayClone(conf.data);}else if(RG.SVG.isArray(conf.data)){this.data=[RG.SVG.arrayClone(conf.data)];}else{this.data=[[]];}
this.type='line';this.coords=[];this.coords2=[];this.coordsSpline=[];this.hasMultipleDatasets=typeof this.data[0]==='object'&&typeof this.data[1]==='object'?true:false;this.colorsParsed=false;this.originalColors={};this.gradientCounter=1;this.originalData=RG.SVG.arrayClone(this.data);this.filledGroups=[];RG.SVG.OR.add(this);this.container.style.display='inline-block';this.properties={gutterLeft:35,gutterRight:35,gutterTop:35,gutterBottom:35,backgroundColor:null,backgroundImage:null,backgroundImageStretch:true,backgroundImageAspect:'none',backgroundImageOpacity:null,backgroundImageX:null,backgroundImageY:null,backgroundImageW:null,backgroundImageH:null,backgroundGrid:true,backgroundGridColor:'#ddd',backgroundGridLinewidth:1,backgroundGridHlines:true,backgroundGridHlinesCount:null,backgroundGridVlines:true,backgroundGridVlinesCount:null,backgroundGridBorder:true,backgroundGridDashed:false,backgroundGridDotted:false,backgroundGridDashArray:null,colors:['red','#0f0','blue','#ff0','#0ff','green'],filled:false,filledColors:[],filledClick:null,filledOpacity:1,filledAccumulative:false,hmargin:0,yaxis:true,yaxisTickmarks:true,yaxisTickmarksLength:3,yaxisColor:'black',yaxisScale:true,yaxisLabels:null,yaxisLabelsOffsetx:0,yaxisLabelsOffsety:0,yaxisLabelsCount:5,yaxisUnitsPre:'',yaxisUnitsPost:'',yaxisStrict:false,yaxisDecimals:0,yaxisPoint:'.',yaxisThousand:',',yaxisRound:false,yaxisMax:null,yaxisMin:0,yaxisFormatter:null,xaxis:true,xaxisTickmarks:true,xaxisTickmarksLength:5,xaxisLabels:null,xaxisLabelsOffsetx:0,xaxisLabelsOffsety:0,xaxisLabelsPosition:'edge',xaxisLabelsPositionEdgeTickmarksCount:null,xaxisColor:'black',textColor:'black',textFont:'sans-serif',textSize:12,textBold:false,textItalic:false,linewidth:1,tooltips:null,tooltipsOverride:null,tooltipsEffect:'fade',tooltipsCssClass:'RGraph_tooltip',tooltipsEvent:'mousemove',highlightStroke:'rgba(0,0,0,0)',highlightFill:'rgba(255,255,255,0.7)',highlightLinewidth:1,tickmarksStyle:'none',tickmarksSize:5,tickmarksFill:'white',tickmarksLinewidth:1,labelsAbove:false,labelsAboveFont:null,labelsAboveSize:null,labelsAboveBold:null,labelsAboveItalic:null,labelsAboveColor:null,labelsAboveBackground:'rgba(255,255,255,0.7)',labelsAboveBackgroundPadding:2,labelsAboveUnitsPre:null,labelsAboveUnitsPost:null,labelsAbovePoint:null,labelsAboveThousand:null,labelsAboveFormatter:null,labelsAboveDecimals:null,labelsAboveOffsetx:0,labelsAboveOffsety:-10,labelsAboveHalign:'center',labelsAboveValign:'bottom',labelsAboveSpecific:null,shadow:false,shadowOffsetx:2,shadowOffsety:2,shadowBlur:2,shadowOpacity:0.25,spline:false,stepped:false,title:'',titleSize:null,titleX:null,titleY:null,titleHalign:'center',titleValign:null,titleColor:null,titleFont:null,titleBold:false,titleItalic:false,titleSubtitle:null,titleSubtitleSize:10,titleSubtitleX:null,titleSubtitleY:null,titleSubtitleHalign:'center',titleSubtitleValign:null,titleSubtitleColor:'#aaa',titleSubtitleFont:null,titleSubtitleBold:false,titleSubtitleItalic:false,errorbars:null,errorbarsColor:'black',errorbarsLinewidth:1,errorbarsCapwidth:10,key:null,keyColors:null,keyOffsetx:0,keyOffsety:0,keyTextOffsetx:0,keyTextOffsety:-1,keyTextSize:null,keyTextBold:null,keyTextItalic:null};RG.SVG.getGlobals(this);if(RG.SVG.FX&&typeof RG.SVG.FX.decorate==='function'){RG.SVG.FX.decorate(this);}
var prop=this.properties;this.draw=function()
{RG.SVG.fireCustomEvent(this,'onbeforedraw');this.width=Number(this.svg.getAttribute('width'));this.height=Number(this.svg.getAttribute('height'));RG.SVG.createDefs(this);this.graphWidth=this.width-prop.gutterLeft-prop.gutterRight;this.graphHeight=this.height-prop.gutterTop-prop.gutterBottom;RG.SVG.resetColorsToOriginalValues({object:this});this.parseColors();this.coords=[];this.coords2=[];this.coordsSpline=[];this.data=RG.SVG.arrayClone(this.originalData);this.tooltipsSequentialIndex=0;for(var i=0,tmp=[];i<this.data.length;++i){for(var j=0;j<this.data[i].length;++j){if(typeof tmp[j]==='undefined'){tmp[j]=0;}
if(prop.filled&&prop.filledAccumulative){tmp[j]+=this.data[i][j];if(i===(this.data.length-1)){tmp[j]+=(prop.errorbars?prop.errorbars[RG.SVG.groupedIndexToSequential({object:this,dataset:i,index:j})].max:0)}}else{tmp[j]=ma.max(tmp[j],this.data[i][j]+(prop.errorbars?prop.errorbars[RG.SVG.groupedIndexToSequential({object:this,dataset:i,index:j})].max:0));}}}
var values=[];for(var i=0,max=0;i<this.data.length;++i){if(RG.SVG.isArray(this.data[i])&&!prop.filledAccumulative){values.push(RG.SVG.arrayMax(tmp));}else if(RG.SVG.isArray(this.data[i])&&prop.filled&&prop.filledAccumulative){for(var j=0;j<this.data[i].length;++j){values[j]=values[j]||0;values[j]=values[j]+this.data[i][j];this.data[i][j]=values[j];}}}
if(prop.filled&&prop.filledAccumulative){var max=RG.SVG.arrayMax(tmp)}else{var max=RG.SVG.arrayMax(values);}
if(typeof prop.yaxisMax==='number'){max=prop.yaxisMax;}
if(prop.yaxisMin==='mirror'){var mirrorScale=true;prop.yaxisMin=0;}
this.scale=RG.SVG.getScale({object:this,numlabels:prop.yaxisLabelsCount,unitsPre:prop.yaxisUnitsPre,unitsPost:prop.yaxisUnitsPost,max:max,min:prop.yaxisMin,point:prop.yaxisPoint,round:prop.yaxisRound,thousand:prop.yaxisThousand,decimals:prop.yaxisDecimals,strict:typeof prop.yaxisMax==='number',formatter:prop.yaxisFormatter});if(mirrorScale){this.scale=RG.SVG.getScale({object:this,numlabels:prop.yaxisLabelsCount,unitsPre:prop.yaxisUnitsPre,unitsPost:prop.yaxisUnitsPost,max:this.scale.max,min:this.scale.max* -1,point:prop.yaxisPoint,round:false,thousand:prop.yaxisThousand,decimals:prop.yaxisDecimals,strict:typeof prop.yaxisMax==='number',formatter:prop.yaxisFormatter});}
this.max=this.scale.max;this.min=this.scale.min;prop.yaxisMax=this.scale.max;prop.yaxisMin=this.scale.min;RG.SVG.drawBackground(this);RG.SVG.drawXAxis(this);RG.SVG.drawYAxis(this);for(var i=0;i<this.data.length;++i){this.drawLine(this.data[i],i);}
this.redrawLines();if(typeof prop.key!==null&&RG.SVG.drawKey){RG.SVG.drawKey(this);}else if(!RGraph.SVG.isNull(prop.key)){alert('The drawKey() function does not exist - have you forgotten to include the key library?');}
this.drawLabelsAbove();var obj=this;document.body.addEventListener('mousedown',function(e)
{RG.SVG.removeHighlight(obj);},false);RG.SVG.fireCustomEvent(this,'ondraw');return this;};this.drawLine=function(data,index)
{var coords=[],path=[];for(var i=0,len=data.length;i<len;++i){var val=data[i],x=(((this.graphWidth-prop.hmargin-prop.hmargin)/(len-1))*i)+prop.gutterLeft+prop.hmargin,y=this.getYCoord(val);coords.push([x,y]);}
for(var i=0;i<coords.length;++i){if(i===0||RG.SVG.isNull(data[i])||RG.SVG.isNull(data[i-1])){var action='M';}else{if(prop.stepped){path.push('L {1} {2}'.format(coords[i][0],coords[i-1][1]));}
var action='L';}
path.push(action+'{1} {2}'.format(coords[i][0],coords[i][1]));}
for(var k=0;k<coords.length;++k){this.coords.push(RG.SVG.arrayClone(coords[k]));this.coords[this.coords.length-1].x=coords[k][0];this.coords[this.coords.length-1].y=coords[k][1];this.coords[this.coords.length-1].object=this;this.coords[this.coords.length-1].value=data[k];this.coords[this.coords.length-1].index=k;this.coords[this.coords.length-1].path=path;}
this.coords2[index]=RG.SVG.arrayClone(coords);for(var k=0;k<coords.length;++k){this.coords2[index][k].x=coords[k][0];this.coords2[index][k].y=coords[k][1];this.coords2[index][k].object=this;this.coords2[index][k].value=data[k];this.coords2[index][k].index=k;this.coords2[index][k].path=path;if(prop.errorbars){this.drawErrorbar({object:this,dataset:index,index:k,x:x,y:y});}}
if(prop.spline){this.coordsSpline[index]=this.drawSpline(coords);}
if(prop.filled===true||(typeof prop.filled==='object'&&prop.filled[index])){if(prop.spline){var fillPath=['M{1} {2}'.format(this.coordsSpline[index][0][0],this.coordsSpline[index][0][1])];for(var i=1;i<this.coordsSpline[index].length;++i){fillPath.push('L{1} {2}'.format(this.coordsSpline[index][i][0]+((i===(this.coordsSpline[index].length)-1)?1:0),this.coordsSpline[index][i][1]));}}else{var fillPath=RG.SVG.arrayClone(path);}
fillPath.push('L{1} {2}'.format(this.coords2[index][this.coords2[index].length-1][0]+1,index>0&&prop.filledAccumulative?(prop.spline?this.coordsSpline[index-1][this.coordsSpline[index-1].length-1][1]:this.coords2[index-1][this.coords2[index-1].length-1][1]):this.getYCoord(prop.yaxisMin>0?prop.yaxisMin:0)+(prop.xaxis?0:1)));if(index>0&&prop.filledAccumulative){var path2=RG.SVG.arrayClone(path);if(index>0&&prop.filledAccumulative){if(prop.spline){for(var i=this.coordsSpline[index-1].length-1;i>=0;--i){fillPath.push('L{1} {2}'.format(this.coordsSpline[index-1][i][0],this.coordsSpline[index-1][i][1]));}}else{for(var i=this.coords2[index-1].length-1;i>=0;--i){fillPath.push('L{1} {2}'.format(this.coords2[index-1][i][0],this.coords2[index-1][i][1]));if(prop.stepped&&i>0){fillPath.push('L{1} {2}'.format(this.coords2[index-1][i][0],this.coords2[index-1][i-1][1]));}}}}}else{fillPath.push('L{1} {2}'.format(this.coords2[index][0][0]+(prop.yaxis?1:0),this.getYCoord(prop.yaxisMin>0?prop.yaxisMin:0)+(prop.xaxis?0:1)));}
fillPath.push('L{1} {2}'.format(this.coords2[index][0][0]+(prop.yaxis?1:0),this.coords2[index][0][1]));for(var i=0;i<this.data[index].length;++i){if(!RG.SVG.isNull(this.data[index][i])){fillPath.push('L{1} {2}'.format(this.coords2[index][i][0],this.getYCoord(0)));break;}}
this.filledGroups[index]=RG.SVG.create({svg:this.svg,type:'g',parent:this.svg.all,attr:{'class':'rgraph_filled_line_'+index}});var fillPathObject=RG.SVG.create({svg:this.svg,parent:this.filledGroups[index],type:'path',attr:{d:fillPath.join(' '),stroke:'rgba(0,0,0,0)','fill':prop.filledColors&&prop.filledColors[index]?prop.filledColors[index]:prop.colors[index],'fill-opacity':prop.filledOpacity,'stroke-width':1,'clip-path':this.isTrace?'url(#trace-effect-clip)':''}});if(prop.filledClick){var obj=this;fillPathObject.addEventListener('click',function(e)
{prop.filledClick(e,obj,index);},false);fillPathObject.addEventListener('mousemove',function(e)
{e.target.style.cursor='pointer';},false);}}
if(prop.shadow){RG.SVG.setShadow({object:this,offsetx:prop.shadowOffsetx,offsety:prop.shadowOffsety,blur:prop.shadowBlur,opacity:prop.shadowOpacity,id:'dropShadow'});}
if(prop.spline){var str=['M{1} {2}'.format(this.coordsSpline[index][0][0],this.coordsSpline[index][0][1])];for(var i=1;i<this.coordsSpline[index].length;++i){str.push('L{1} {2}'.format(this.coordsSpline[index][i][0],this.coordsSpline[index][i][1]));}
str=str.join(' ');var line=RG.SVG.create({svg:this.svg,parent:prop.filled?this.filledGroups[index]:this.svg.all,type:'path',attr:{d:str,stroke:prop['colors'][index],'fill':'none','stroke-width':this.hasMultipleDatasets&&prop.filled&&prop.filledAccumulative?0.1:(RG.SVG.isArray(prop.linewidth)?prop.linewidth[index]:prop.linewidth+0.01),'stroke-linecap':'round','stroke-linejoin':'round',filter:prop.shadow?'url(#dropShadow)':'','clip-path':this.isTrace?'url(#trace-effect-clip)':''}});}else{var path2=RG.SVG.arrayClone(path);if(prop.filled&&prop.filledAccumulative&&index>0){for(var i=this.coords2[index-1].length-1;i>=0;--i){path2.push('L{1} {2}'.format(this.coords2[index-1][i][0],this.coords2[index-1][i][1]));}}
path2=path2.join(' ');var line=RG.SVG.create({svg:this.svg,parent:prop.filled?this.filledGroups[index]:this.svg.all,type:'path',attr:{d:path2,stroke:prop.colors[index],'fill':'none','stroke-width':this.hasMultipleDatasets&&prop.filled&&prop.filledAccumulative?0.1:(RG.SVG.isArray(prop.linewidth)?prop.linewidth[index]:prop.linewidth+0.01),'stroke-linecap':'round','stroke-linejoin':'round',filter:prop.shadow?'url(#dropShadow)':'','clip-path':this.isTrace?'url(#trace-effect-clip)':''}});}
if(prop.tooltips&&prop.tooltips.length){var group=RG.SVG.create({svg:this.svg,parent:this.svg.all,type:'g',attr:{'fill':'transparent',className:"rgraph_hotspots"},style:{cursor:'pointer'}});for(var i=0;i<this.coords2[index].length&&this.tooltipsSequentialIndex<prop.tooltips.length;++i,++this.tooltipsSequentialIndex){if(prop.tooltips[this.tooltipsSequentialIndex]&&this.coords2[index][i][0]&&this.coords2[index][i][1]){var hotspot=RG.SVG.create({svg:this.svg,type:'circle',attr:{cx:this.coords2[index][i][0],cy:this.coords2[index][i][1],r:10,fill:'transparent','data-dataset':index,'data-index':i},style:{cursor:'pointer'}});var obj=this;(function(sequentialIndex)
{hotspot.addEventListener(prop.tooltipsEvent,function(e)
{var indexes=RG.SVG.sequentialIndexToGrouped(sequentialIndex,obj.data),index=indexes[1],dataset=indexes[0];if(RG.SVG.REG.get('tooltip')&&RG.SVG.REG.get('tooltip').__index__===index&&RG.SVG.REG.get('tooltip').__dataset__===dataset){return;}
obj.removeHighlight();RG.SVG.hideTooltip();if(prop.tooltips[sequentialIndex]){var text=prop.tooltips[sequentialIndex];}
RG.SVG.tooltip({object:obj,index:index,dataset:dataset,sequentialIndex:sequentialIndex,text:text,event:e});var outer_highlight1=RG.SVG.create({svg:obj.svg,parent:obj.svg.all,type:'circle',attr:{cx:obj.coords2[dataset][index][0],cy:obj.coords2[dataset][index][1],r:13,fill:obj.properties.colors[dataset],'fill-opacity':0.5},style:{cursor:'pointer'}});var outer_highlight2=RG.SVG.create({svg:obj.svg,parent:obj.svg.all,type:'circle',attr:{cx:obj.coords2[dataset][index][0],cy:obj.coords2[dataset][index][1],r:14,fill:'white','fill-opacity':0.75},style:{cursor:'pointer'}});var inner_highlight1=RG.SVG.create({svg:obj.svg,parent:obj.svg.all,type:'circle',attr:{cx:obj.coords2[dataset][index][0],cy:obj.coords2[dataset][index][1],r:6,fill:'white'},style:{cursor:'pointer'}});var inner_highlight2=RG.SVG.create({svg:obj.svg,parent:obj.svg.all,type:'circle',attr:{cx:obj.coords2[dataset][index][0],cy:obj.coords2[dataset][index][1],r:5,fill:obj.properties.colors[dataset]},style:{cursor:'pointer'}});RG.SVG.REG.set('highlight',[outer_highlight1,outer_highlight2,inner_highlight1,inner_highlight2]);},false);})(this.tooltipsSequentialIndex);}}}};this.drawTickmarks=function(index,data,coords)
{for(var i=0;i<data.length;++i){if(typeof data[i]==='number'){switch(prop.tickmarksStyle){case'filledcircle':case'filledendcircle':if(prop.tickmarksStyle==='filledcircle'||(i===0||i===data.length-1)){var circle=RG.SVG.create({svg:this.svg,type:'circle',attr:{cx:coords[index][i][0],cy:coords[index][i][1],r:prop.tickmarksSize,'fill':prop.colors[index],filter:prop.shadow?'url(#dropShadow)':'','clip-path':this.isTrace?'url(#trace-effect-clip)':''}});}
break;case'circle':case'endcircle':if(prop.tickmarksStyle==='circle'||(prop.tickmarksStyle==='endcircle'&&(i===0||i===data.length-1))){var outerCircle=RG.SVG.create({svg:this.svg,parent:this.svg.all,type:'circle',attr:{cx:coords[index][i][0],cy:coords[index][i][1],r:prop.tickmarksSize+prop.tickmarksLinewidth,'fill':prop.colors[index],filter:prop.shadow?'url(#dropShadow)':'','clip-path':this.isTrace?'url(#trace-effect-clip)':''}});var innerCircle=RG.SVG.create({svg:this.svg,parent:this.svg.all,type:'circle',attr:{cx:coords[index][i][0],cy:coords[index][i][1],r:prop.tickmarksSize,'fill':prop.tickmarksFill,'clip-path':this.isTrace?'url(#trace-effect-clip)':''}});break;}
break;case'endrect':case'rect':if(prop.tickmarksStyle==='rect'||(prop.tickmarksStyle==='endrect'&&(i===0||i===data.length-1))){var half=(prop.tickmarksSize+prop.tickmarksLinewidth)/2;var fill=typeof prop.tickmarksFill==='object'&&typeof prop.tickmarksFill[index]==='string'?prop.tickmarksFill[index]:prop.tickmarksFill;var rect=RG.SVG.create({svg:this.svg,parent:this.svg.all,type:'rect',attr:{x:coords[index][i][0]-half,y:coords[index][i][1]-half,width:prop.tickmarksSize+prop.tickmarksLinewidth,height:prop.tickmarksSize+prop.tickmarksLinewidth,'stroke-width':prop.tickmarksLinewidth,'stroke':prop.colors[index],'fill':fill,'clip-path':this.isTrace?'url(#trace-effect-clip)':''}});}
break;case'filledendrect':case'filledrect':if(prop.tickmarksStyle==='filledrect'||(prop.tickmarksStyle==='filledendrect'&&(i===0||i===data.length-1))){var half=(prop.tickmarksSize+prop.tickmarksLinewidth)/2;var fill=prop.colors[index];var rect=RG.SVG.create({svg:this.svg,parent:this.svg.all,type:'rect',attr:{x:coords[index][i][0]-half,y:coords[index][i][1]-half,width:prop.tickmarksSize+prop.tickmarksLinewidth,height:prop.tickmarksSize+prop.tickmarksLinewidth,'fill':fill,'clip-path':this.isTrace?'url(#trace-effect-clip)':''}});}}}}};this.redrawLines=function()
{if(prop.spline){for(var i=0;i<this.coordsSpline.length;++i){var linewidth=RG.SVG.isArray(prop.linewidth)?prop.linewidth[i]:prop.linewidth,color=prop['colors'][i],path='';for(var j=0;j<this.coordsSpline[i].length;++j){if(j===0){path+='M{1} {2} '.format(this.coordsSpline[i][j][0],this.coordsSpline[i][j][1]);}else{path+='L{1} {2} '.format(this.coordsSpline[i][j][0],this.coordsSpline[i][j][1]);}}
RG.SVG.create({svg:this.svg,parent:prop.filled?this.filledGroups[i]:this.svg.all,type:'path',attr:{d:path,stroke:color,'fill':'none','stroke-width':linewidth+0.01,'stroke-linecap':'round','stroke-linejoin':'round',filter:prop.shadow?'url(#dropShadow)':'','clip-path':this.isTrace?'url(#trace-effect-clip)':''}});}
for(var dataset=0;dataset<this.coords2.length;++dataset){this.drawTickmarks(dataset,this.data[dataset],this.coords2);}}else{for(var i=0;i<this.coords2.length;++i){var linewidth=RG.SVG.isArray(prop.linewidth)?prop.linewidth[i]:prop.linewidth,color=prop['colors'][i],path='';for(var j=0;j<this.coords2[i].length;++j){if(j===0||RG.SVG.isNull(this.data[i][j])||RG.SVG.isNull(this.data[i][j-1])){path+='M{1} {2} '.format(this.coords2[i][j][0],this.coords2[i][j][1]);}else{if(prop.stepped){path+='L{1} {2} '.format(this.coords2[i][j][0],this.coords2[i][j-1][1]);}
path+='L{1} {2} '.format(this.coords2[i][j][0],this.coords2[i][j][1]);}}
RG.SVG.create({svg:this.svg,parent:prop.filled?this.filledGroups[i]:this.svg.all,type:'path',attr:{d:path,stroke:color,'fill':'none','stroke-width':linewidth+0.01,'stroke-linecap':'round','stroke-linejoin':'round',filter:prop.shadow?'url(#dropshadow)':'','clip-path':this.isTrace?'url(#trace-effect-clip)':''}});}
for(var dataset=0;dataset<this.coords2.length;++dataset){this.drawTickmarks(dataset,this.data[dataset],this.coords2);}}};this.getYCoord=function(value)
{var prop=this.properties,y;if(value>this.scale.max){return null;}
if(value<this.scale.min){return null;}
y=((value-this.scale.min)/(this.scale.max-this.scale.min));y*=(this.height-prop.gutterTop-prop.gutterBottom);y=this.height-prop.gutterBottom-y;return y;};this.highlight=function(rect)
{var x=rect.getAttribute('x'),y=rect.getAttribute('y');};this.removeHighlight=function()
{var highlight=RG.SVG.REG.get('highlight');if(highlight&&highlight.parentNode){highlight.parentNode.removeChild(highlight);}else if(highlight){for(var i=0;i<highlight.length;++i){if(highlight[i]&&highlight[i].parentNode){highlight[i].parentNode.removeChild(highlight[i]);}}}
RG.SVG.REG.set('highlight',null);};this.drawSpline=function(coords)
{var xCoords=[];gutterLeft=prop.gutterLeft,gutterRight=prop.gutterRight,hmargin=prop.hmargin,interval=(this.graphWidth-(2*hmargin))/(coords.length-1),coordsSpline=[];for(var i=0,len=coords.length;i<len;i+=1){if(typeof coords[i]=='object'&&coords[i]&&coords[i].length==2){coords[i]=Number(coords[i][1]);}}
var P=[coords[0]];for(var i=0;i<coords.length;++i){P.push(coords[i]);}
P.push(coords[coords.length-1]+(coords[coords.length-1]-coords[coords.length-2]));for(var j=1;j<P.length-2;++j){for(var t=0;t<10;++t){var yCoord=spline(t/10,P[j-1],P[j],P[j+1],P[j+2]);xCoords.push(((j-1)*interval)+(t*(interval/10))+gutterLeft+hmargin);coordsSpline.push([xCoords[xCoords.length-1],yCoord]);if(typeof index==='number'){coordsSpline[index].push([xCoords[xCoords.length-1],yCoord]);}}}
coordsSpline.push([((j-1)*interval)+gutterLeft+hmargin,P[j]]);if(typeof index==='number'){coordsSpline.push([((j-1)*interval)+gutterLeft+hmargin,P[j]]);}
function spline(t,P0,P1,P2,P3)
{return 0.5*((2*P1)+
((0-P0)+P2)*t+
((2*P0-(5*P1)+(4*P2)-P3)*(t*t)+
((0-P0)+(3*P1)-(3*P2)+P3)*(t*t*t)));}
for(var i=0;i<coordsSpline.length;++i){coordsSpline[i].object=this;coordsSpline[i].x=this;coordsSpline[i].y=this;}
return coordsSpline;};this.parseColors=function()
{if(!Object.keys(this.originalColors).length){this.originalColors={colors:RG.SVG.arrayClone(prop.colors),filledColors:RG.SVG.arrayClone(prop.filledColors),backgroundGridColor:RG.SVG.arrayClone(prop.backgroundGridColor),highlightFill:RG.SVG.arrayClone(prop.highlightFill),backgroundColor:RG.SVG.arrayClone(prop.backgroundColor)}}
var colors=prop.colors;if(colors){for(var i=0;i<colors.length;++i){colors[i]=RG.SVG.parseColorLinear({object:this,color:colors[i]});}}
var filledColors=prop.filledColors;if(filledColors){for(var i=0;i<filledColors.length;++i){filledColors[i]=RG.SVG.parseColorLinear({object:this,color:filledColors[i]});}}
prop.backgroundGridColor=RG.SVG.parseColorLinear({object:this,color:prop.backgroundGridColor});prop.highlightFill=RG.SVG.parseColorLinear({object:this,color:prop.highlightFill});prop.backgroundColor=RG.SVG.parseColorLinear({object:this,color:prop.backgroundColor});};this.drawLabelsAbove=function()
{if(prop.labelsAbove){var data_seq=RG.SVG.arrayLinearize(this.data),seq=0;for(var dataset=0;dataset<this.coords2.length;++dataset,seq++){for(var i=0;i<this.coords2[dataset].length;++i,seq++){var str=RG.SVG.numberFormat({object:this,num:this.data[dataset][i].toFixed(prop.labelsAboveDecimals),prepend:typeof prop.labelsAboveUnitsPre==='string'?prop.labelsAboveUnitsPre:null,append:typeof prop.labelsAboveUnitsPost==='string'?prop.labelsAboveUnitsPost:null,point:typeof prop.labelsAbovePoint==='string'?prop.labelsAbovePoint:null,thousand:typeof prop.labelsAboveThousand==='string'?prop.labelsAboveThousand:null,formatter:typeof prop.labelsAboveFormatter==='function'?prop.labelsAboveFormatter:null});if(prop.labelsAboveSpecific&&prop.labelsAboveSpecific.length&&(typeof prop.labelsAboveSpecific[seq]==='string'||typeof prop.labelsAboveSpecific[seq]==='number')){str=prop.labelsAboveSpecific[seq];}else if(prop.labelsAboveSpecific&&prop.labelsAboveSpecific.length&&typeof prop.labelsAboveSpecific[seq]!=='string'&&typeof prop.labelsAboveSpecific[seq]!=='number'){continue;}
RG.SVG.text({object:this,parent:this.svg.all,tag:'labels.above',text:str,x:parseFloat(this.coords2[dataset][i][0])+prop.labelsAboveOffsetx,y:parseFloat(this.coords2[dataset][i][1])+prop.labelsAboveOffsety,halign:prop.labelsAboveHalign,valign:prop.labelsAboveValign,font:prop.labelsAboveFont||prop.textFont,size:prop.labelsAboveSize||prop.textSize,bold:prop.labelsAboveBold||prop.textBold,italic:prop.labelsAboveItalic||prop.textItalic,color:prop.labelsAboveColor||prop.textColor,background:prop.labelsAboveBackground||null,padding:prop.labelsAboveBackgroundPadding||0});}
seq--;}}};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
RG.SVG.addCustomEventListener(this,type,func);return this;};this.exec=function(func)
{func(this);return this;};this.drawErrorbar=function(opt)
{var linewidth=RG.SVG.getErrorbarsLinewidth({object:this,index:opt.index}),color=RG.SVG.getErrorbarsColor({object:this,index:opt.index}),capwidth=RG.SVG.getErrorbarsCapWidth({object:this,index:opt.index}),index=opt.index,dataset=opt.dataset,x=opt.x,y=opt.y,value=this.data[dataset][index],seq=RG.SVG.groupedIndexToSequential({dataset:dataset,index:index,object:this});var y=this.getYCoord(y);var max=RG.SVG.getErrorbarsMaxValue({object:this,index:seq});var min=RG.SVG.getErrorbarsMinValue({object:this,index:seq});if(!max&&!min){return;}
var x=this.coords2[dataset][index].x,y=this.coords2[dataset][index].y,halfCapWidth=capwidth/2,y1=this.getYCoord(value+max),y3=this.getYCoord(value-min)===null?y:this.getYCoord(value-min);if(max>0){var errorbarLine=RG.SVG.create({svg:this.svg,type:'line',parent:this.svg.all,attr:{x1:x,y1:y,x2:x,y2:y1,stroke:color,'stroke-width':linewidth}});var errorbarCap=RG.SVG.create({svg:this.svg,type:'line',parent:this.svg.all,attr:{x1:x-halfCapWidth,y1:y1,x2:x+halfCapWidth,y2:y1,stroke:color,'stroke-width':linewidth}});}
if(typeof min==='number'){var errorbarLine=RG.SVG.create({svg:this.svg,type:'line',parent:this.svg.all,attr:{x1:x,y1:y,x2:x,y2:y3,stroke:color,'stroke-width':linewidth}});var errorbarCap=RG.SVG.create({svg:this.svg,type:'line',parent:this.svg.all,attr:{x1:x-halfCapWidth,y1:y3,x2:x+halfCapWidth,y2:y3,stroke:color,'stroke-width':linewidth}});}};this.trace=function()
{var opt=arguments[0]||{},frame=1,frames=opt.frames||60,obj=this;this.isTrace=true;this.draw();var clipPath=RG.SVG.create({svg:this.svg,parent:this.svg.defs,type:'clipPath',attr:{id:'trace-effect-clip'}});var clipPathRect=RG.SVG.create({svg:this.svg,parent:clipPath,type:'rect',attr:{x:0,y:0,width:0,height:this.height}});var iterator=function()
{var width=(frame++)/frames*obj.width;clipPathRect.setAttribute("width",width);if(frame<=frames){RG.SVG.FX.update(iterator);}else if(opt.callback){(opt.callback)(obj);}};iterator();return this;};for(i in conf.options){if(typeof i==='string'){this.set(i,conf.options[i]);}}}
return this;})(window,document);