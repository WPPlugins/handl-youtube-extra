var tag = document.createElement('script');

tag.src = "https://www.youtube.com/iframe_api";
var firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

var ticks = {};
//var players = [];
function onYouTubeIframeAPIReady() {
  HandLYTExtraLoadPlayers()
}

function HandLYTonPlayerReady(event) {
  //event.target.playVideo();
  //do nothing for now...
}

function HandLYTonPlayerStateChange(event) {
  ytt = event
  if (event.data == YT.PlayerState.PLAYING) {
		  
      HandLYTExtraSaveVideoData(event)
      var tick = setInterval(function(){
	  HandLYTExtraSaveVideoData(event)
      }, 1000);
      
      ticks[ytt.target.a.id] = tick
      
  }else if (event.data == YT.PlayerState.PAUSED) {
      clearInterval(ticks[ytt.target.a.id]);
  }else if (event.data == YT.PlayerState.ENDED) {
      clearInterval(ticks[ytt.target.a.id])
  }
}

function HandLYTExtraSaveVideoData(event){
  var p = event.target
  var data = {action: 'handl_yt_save_video_data', postID:handl_yt.postID, d:Math.round(p.getDuration()), c:Math.round(p.getCurrentTime()), i:p.getVideoData().video_id, t:p.getVideoData().title};
  
  //console.log(data);
  
  jQuery.ajax({
          url: handl_yt.ajaxurl,
          data: data,
          type: 'POST',
          dataType: 'json',
          success: function(data){
              
          }
  });
}

function HandLYTExtraLoadPlayers(){
  jQuery( document ).ready(function($) {
      $.each($('.handl-yt'), function( index, i ) {
	
	var YTPlayerVars = {}
	jQuery.each($(i)[0].attributes, function( pi, pp ) {
	    var fields = pp.name.split('-params-');
	    if (fields.length == 2){
		YTPlayerVars[fields[1]] = pp.value
	    }
	});
	
	player = new YT.Player(i.id, {
	    height: $(i).attr('data-handl-yt-height'),
	    width: $(i).attr('data-handl-yt-width'),
	    videoId: $(i).attr('data-handl-yt-videoid'),
	    playerVars: YTPlayerVars,
	    events: {
		'onReady': HandLYTonPlayerReady,  
		'onStateChange': HandLYTonPlayerStateChange
	    }
	});
	  //players.push(player)
      });
  });
  
}