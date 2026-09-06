'use strict';

// A procedural village, shared by every weather scene. Coordinates are independent of pixel density.
window.AtmosLandscape = class {
    constructor(context) { this.ctx = context; }
    static season(temperature, month, latitude = null) {
        // Month is local to the station; shift the calendar for the southern hemisphere.
        const localMonth = latitude < 0 ? (month + 5) % 12 + 1 : month;
        const calendar = localMonth <= 2 || localMonth === 12 ? 'winter'
            : localMonth <= 5 ? 'spring' : localMonth <= 8 ? 'summer' : 'autumn';
        if (!Number.isFinite(temperature)) return calendar;
        if (temperature <= 3) return 'winter';
        if (temperature >= 22) return 'summer';
        if (calendar === 'winter') return temperature <= 10 ? 'winter' : 'spring';
        if (calendar === 'summer' && temperature < 16) return localMonth <= 7 ? 'spring' : 'autumn';
        return calendar;
    }
    draw(w, h, time, weather, phase, temperature = null, season = 'summer') {
        const ctx = this.ctx;
        // Snow-covered terrain and falling snow are independent: a cold, clear day stays sunny.
        const snow = weather === 'snow' || (Number.isFinite(temperature) && temperature <= 3);
        const winter = season === 'winter' || snow;
        const spring = season === 'spring' && !snow;
        const autumn = season === 'autumn' && !snow;
        const night = phase === 'night';
        const evening = phase === 'twilight';
        const wet = weather === 'rain' || weather === 'storm';
        // Heating activity fades out between freezing and 14 °C; missing readings add no smoke.
        const smoke = Number.isFinite(temperature) ? Math.max(0, Math.min(1, (14 - temperature) / 14)) : 0;
        const muted = wet || weather === 'fog' || weather === 'cloudy' || weather === 'unknown';
        const colors = night ? {
            far:'#344854', near:'#2d434a', mountainLight:'#60757c', hill:'#28433c', meadow:'#213d32', front:'#193328',
            wall:'#606760', side:'#454e49', roof:'#343e3c', path:'#5a6657', tree:'#1b3931', trunk:'#454c40', snow:'#a4bac2'
        } : evening ? {
            far:'#847887', near:'#626a71', mountainLight:'#b6a39c', hill:'#52604a', meadow:'#42583b', front:'#2e4932',
            wall:'#ccac88', side:'#9a8268', roof:'#92634b', path:'#a8976a', tree:'#345140', trunk:'#675340', snow:'#dbcecb'
        } : {
            far:muted?'#728a90':'#86a6b9', near:muted?'#5f7a7c':'#628b94', mountainLight:'#d6e0df',
            hill:muted?'#547762':'#73a460', meadow:muted?'#466c54':'#5c944d', front:muted?'#355341':'#3f753e',
            wall:'#eee1c8', side:'#c9ba9f', roof:'#b16d4b', path:'#bead7d', tree:'#3c7048', trunk:'#7b6247', snow:'#e0e9e6'
        };
        const seasonal = {
            winter: {hill:'#879b99', meadow:'#7d9285', front:'#627e72', leaf:'#829b91', leafLight:'#b5c3b0', blossom:'#f2e7d4'},
            spring: {hill:'#86b773', meadow:'#70a955', front:'#528747', leaf:'#90b95f', leafLight:'#bed88a', blossom:'#f7d7dd'},
            summer: {hill:'#73a460', meadow:'#5c944d', front:'#3f753e', leaf:'#578748', leafLight:'#82a75a', blossom:'#f2e6b1'},
            autumn: {hill:'#ac9861', meadow:'#8f874e', front:'#6d7146', leaf:'#c07b39', leafLight:'#e0ab51', blossom:'#bd5937'}
        }[winter ? 'winter' : season];
        const tint = (hex, shade, amount) => {
            const channels = [1, 3, 5].map(start => Math.round(parseInt(hex.slice(start, start + 2), 16) * (1 - amount)
                + parseInt(shade.slice(start, start + 2), 16) * amount).toString(16).padStart(2, '0'));
            return '#' + channels.join('');
        };
        for (const [key, color] of Object.entries(seasonal)) {
            colors[key] = night ? tint(color, '#142d36', .68) : evening ? tint(color, '#664d60', .4)
                : muted ? tint(color, '#526b73', .36) : color;
        }
        if (winter) {
            colors.far = night ? '#3c5368' : evening ? '#9a8f9e' : muted ? '#91a4b1' : '#9fbdd3';
            colors.near = night ? '#344d61' : evening ? '#7e8292' : muted ? '#768e9c' : '#789daf';
            colors.mountainLight = night ? '#96b3c7' : evening ? '#eddde0' : '#edf3f3';
            colors.snow = night ? '#9db7c7' : evening ? '#dccfd6' : '#dceaf0';
        }
        const polygon = (points, color) => {
            ctx.beginPath(); points.forEach(([x,y],i)=>i ? ctx.lineTo(x*w,y*h) : ctx.moveTo(x*w,y*h));
            ctx.closePath(); ctx.fillStyle=color; ctx.fill();
        };
        ctx.save();
        // Two mountain ridges, with snow on the distant peaks.
        polygon([[0,.62],[.08,.54],[.18,.56],[.28,.46],[.35,.50],[.44,.39],[.52,.46],[.60,.42],[.70,.52],[.80,.40],[.88,.48],[.96,.44],[1,.49],[1,1],[0,1]],colors.far);
        polygon([[.395,.443],[.44,.39],[.483,.435],[.455,.423],[.436,.429],[.426,.414]],colors.mountainLight);
        polygon([[.76,.445],[.80,.40],[.845,.443],[.807,.425],[.792,.435]],colors.mountainLight);
        if (winter) {
            polygon([[.375,.467],[.44,.39],[.52,.46],[.481,.451],[.456,.47],[.422,.437],[.405,.466]],colors.mountainLight);
            polygon([[.738,.47],[.80,.40],[.865,.468],[.828,.453],[.795,.472],[.778,.443]],colors.mountainLight);
            polygon([[.557,.443],[.60,.42],[.64,.46],[.603,.444],[.586,.454]],colors.mountainLight);
        }
        polygon([[0,.67],[.11,.59],[.22,.62],[.31,.55],[.39,.60],[.51,.51],[.60,.58],[.69,.54],[.81,.61],[.93,.53],[1,.58],[1,1],[0,1]],colors.near);
        // Soft hills make a clear foreground between the village and the mountains.
        ctx.beginPath();ctx.moveTo(0,h*.68);ctx.bezierCurveTo(w*.18,h*.54,w*.28,h*.73,w*.50,h*.63);
        ctx.bezierCurveTo(w*.71,h*.52,w*.85,h*.60,w,h*.65);ctx.lineTo(w,h);ctx.lineTo(0,h);ctx.closePath();
        ctx.fillStyle=snow?colors.snow:colors.hill;ctx.fill();
        ctx.beginPath();ctx.moveTo(0,h*.76);ctx.bezierCurveTo(w*.20,h*.62,w*.40,h*.79,w*.62,h*.71);
        ctx.bezierCurveTo(w*.80,h*.64,w*.92,h*.72,w,h*.70);ctx.lineTo(w,h);ctx.lineTo(0,h);ctx.closePath();
        ctx.fillStyle=snow?(night?'#b2c8d5':evening?'#eee0de':'#f0f5f5'):colors.meadow;ctx.fill();
        ctx.save();
        ctx.translate(0,-h*.04);
        const size=Math.min(1.25,Math.max(.78,w/620));
        // A curved lane links the houses and catches lamps and rain reflections.
        ctx.beginPath();ctx.moveTo(w*.73,h*.70);ctx.bezierCurveTo(w*.72,h*.80,w*.85,h*.82,w*.46,h);
        ctx.lineWidth=6*size;ctx.strokeStyle=snow?'#7f979659':colors.path+'70';ctx.stroke();
        const pine=(x,y,height,index)=>{
            ctx.save();ctx.translate(x,y);ctx.rotate(Math.sin(time*.55+index)*.012);
            ctx.fillStyle=colors.trunk;ctx.fillRect(-1, -height*.30, 2, height*.30);
            for(let tier=0;tier<3;tier++){
                const top=-height+tier*height*.21, half=height*(.20+tier*.028);
                ctx.beginPath();ctx.moveTo(0,top);ctx.lineTo(half,top+height*.46);ctx.lineTo(-half,top+height*.46);ctx.closePath();
                ctx.fillStyle=colors.tree;ctx.fill();
                if(snow){ctx.beginPath();ctx.moveTo(0,top);ctx.lineTo(half*.80,top+height*.34);ctx.lineTo(half*.22,top+height*.29);ctx.lineTo(-half*.5,top+height*.34);ctx.closePath();ctx.fillStyle=colors.snow;ctx.fill();}
            }
            ctx.restore();
        };
        // Trees behind the homes, with subtle independent wind motion.
        [[.44,.72,44],[.49,.695,37],[.84,.70,47],[.96,.72,43],[.91,.69,33]].forEach(([x,y,z],i)=>pine(w*x,h*y,z*size,i));
        const broadleaf = (nx, ny, height, index) => {
            const x = w * nx, y = h * ny, z = height * size;
            ctx.save();ctx.translate(x,y);ctx.rotate(Math.sin(time * .45 + index) * .016);
            ctx.lineCap='round';ctx.strokeStyle=colors.trunk;ctx.lineWidth=2.1*size;
            ctx.beginPath();ctx.moveTo(0,0);ctx.quadraticCurveTo(-z*.05,-z*.35,z*.02,-z*.86);
            for(let branch=0;branch<4;branch++){
                const side=branch%2?1:-1, root=-z*(.28+branch*.12), tipX=side*z*(.22-branch*.025), tipY=root-z*.23;
                ctx.moveTo(0,root);ctx.lineTo(tipX,tipY);ctx.lineTo(tipX*1.3,tipY-z*.10);
                ctx.moveTo(tipX*.7,root-z*.16);ctx.lineTo(tipX*.9,root-z*.32);
            }ctx.stroke();
            if(winter){
                if(snow){ctx.strokeStyle=colors.snow;ctx.lineWidth=1.4*size;ctx.beginPath();ctx.moveTo(-z*.02,-z*.39);ctx.lineTo(-z*.2,-z*.57);ctx.moveTo(0,-z*.52);ctx.lineTo(z*.18,-z*.7);ctx.stroke();}
            }else{
                [[-.14,-.64,.22],[.16,-.66,.23],[.015,-.83,.235],[-.05,-.59,.21]].forEach(([tx,ty,r],i)=>{
                    ctx.beginPath();ctx.ellipse(tx*z,ty*z,z*r,z*r*1.1,0,0,Math.PI*2);
                    ctx.fillStyle=i%2?colors.leafLight:colors.leaf;ctx.fill();
                });
                // Blossoms in spring, copper clusters and a few drifting leaves in autumn.
                if(spring || autumn){
                    ctx.fillStyle=colors.blossom;
                    for(let dot=0;dot<12;dot++){
                        const a=dot*2.4+index,r=z*(.055+(dot%4)*.05);
                        ctx.beginPath();ctx.arc(Math.cos(a)*r,-z*.73+Math.sin(a)*r,(spring?1.6:2.4)*size,0,Math.PI*2);ctx.fill();
                    }
                }
                if(autumn){
                    for(let leaf=0;leaf<3;leaf++){
                        const fall=((time*.07+leaf*.31+index*.17)%1),lx=Math.sin(time*.65+leaf+index)*z*.25+fall*z*.18;
                        ctx.beginPath();ctx.ellipse(lx,-z*.64+fall*z*.69,2*size,.85*size,time*.7+leaf,0,Math.PI*2);
                        ctx.fillStyle=leaf%2?colors.leafLight:colors.blossom;ctx.fill();
                    }
                }
            }
            ctx.restore();
        };
        [[.10,.765,48],[.27,.722,38],[.635,.742,37],[.89,.735,34]].forEach(([x,y,z],i)=>broadleaf(x,y,z,i));
        const lit=(index)=> (night||evening) && (index%4!==2 || Math.sin(time*.035+index)>-.2);
        const windowPane=(x,y,width,height,on)=>{
            ctx.save();
            if(on){ctx.shadowColor='#ffd786';ctx.shadowBlur=8*size;}
            ctx.fillStyle=on?'#ffdc92':(night?'#263935':'#79969b');ctx.fillRect(x,y,width,height);
            ctx.shadowBlur=0;ctx.strokeStyle=on?'#927550':'#e2d8c0';ctx.lineWidth=.6*size;
            ctx.beginPath();ctx.moveTo(x+width/2,y);ctx.lineTo(x+width/2,y+height);ctx.stroke();ctx.restore();
        };
        const house=(nx,ny,width,height,index)=>{
            const x=w*nx,y=h*ny,bw=width*size,bh=height*size,roof=bh*.49;
            ctx.fillStyle=colors.side;ctx.fillRect(x,y-bh,bw,bh);
            ctx.fillStyle=colors.wall;ctx.fillRect(x,y-bh,bw*.78,bh);
            // Chimney, pitched roof and winter accumulation.
            ctx.fillStyle=colors.side;ctx.fillRect(x+bw*.65,y-bh-roof*.75,bw*.13,roof*.7);
            ctx.beginPath();ctx.moveTo(x-3*size,y-bh);ctx.lineTo(x+bw*.4,y-bh-roof);ctx.lineTo(x+bw+3*size,y-bh);ctx.closePath();ctx.fillStyle=colors.roof;ctx.fill();
            if(snow){ctx.strokeStyle=colors.snow;ctx.lineWidth=3.4*size;ctx.lineCap='round';ctx.beginPath();ctx.moveTo(x-2*size,y-bh);ctx.lineTo(x+bw*.4,y-bh-roof);ctx.lineTo(x+bw+2*size,y-bh);ctx.stroke();}
            ctx.fillStyle=night?'#293d36':'#856e52';ctx.fillRect(x+bw*.42,y-bh*.40,bw*.18,bh*.40);
            windowPane(x+bw*.12,y-bh*.72,bw*.17,bh*.22,lit(index));
            windowPane(x+bw*.51,y-bh*.72,bw*.17,bh*.22,lit(index+1));
            if(bh>23*size)windowPane(x+bw*.13,y-bh*.33,bw*.15,bh*.18,lit(index+2));
            // Chimney smoke follows the displayed temperature in every scene.
            if(smoke > 0){
                ctx.save();ctx.globalAlpha=smoke;ctx.strokeStyle=night?'#cddada60':'#e1e7dc80';ctx.lineWidth=(1.3+smoke*1.4)*size;ctx.lineCap='round';
                for(let plume=0;plume<2;plume++){
                    const sx=x+bw*.71,sy=y-bh-roof*.78-plume*9*size,drift=Math.sin(time*.7+index+plume)*5*size;
                    ctx.beginPath();ctx.moveTo(sx,sy);ctx.bezierCurveTo(sx+drift-7*size,sy-8*size,sx+drift+12*size,sy-16*size,sx+drift+5*size,sy-26*size);ctx.stroke();
                }ctx.restore();
            }
        };
        house(.47,.724,31,23,0);
        house(.565,.739,37,29,4);
        house(.80,.726,34,25,7);
        house(.905,.747,29,22,10);
        // The village church, including a clock, steeple and lit windows.
        const cx=w*.70,cy=h*.718,sw=15*size,th=49*size;
        ctx.fillStyle=colors.side;ctx.fillRect(cx,cy-th,sw,th);
        ctx.fillStyle=colors.wall;ctx.fillRect(cx,cy-th,sw*.77,th);ctx.fillRect(cx+sw,cy-22*size,28*size,22*size);
        ctx.beginPath();ctx.moveTo(cx-2*size,cy-th);ctx.lineTo(cx+sw*.5,cy-th-21*size);ctx.lineTo(cx+sw+2*size,cy-th);ctx.closePath();ctx.fillStyle=colors.roof;ctx.fill();
        ctx.beginPath();ctx.moveTo(cx+sw-2*size,cy-22*size);ctx.lineTo(cx+sw+13*size,cy-36*size);ctx.lineTo(cx+sw+31*size,cy-22*size);ctx.closePath();ctx.fill();
        ctx.strokeStyle=snow?colors.snow:colors.roof;ctx.lineWidth=snow?2.7*size:1;ctx.beginPath();ctx.moveTo(cx+sw-1*size,cy-22*size);ctx.lineTo(cx+sw+13*size,cy-36*size);ctx.lineTo(cx+sw+30*size,cy-22*size);ctx.stroke();
        ctx.strokeStyle=night?'#9eaa92':'#746a4b';ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(cx+sw*.5,cy-th-28*size);ctx.lineTo(cx+sw*.5,cy-th-19*size);ctx.moveTo(cx+sw*.5-3*size,cy-th-25*size);ctx.lineTo(cx+sw*.5+3*size,cy-th-25*size);ctx.stroke();
        ctx.beginPath();ctx.arc(cx+sw*.4,cy-th+10*size,3.6*size,0,Math.PI*2);ctx.fillStyle=night?'#d0bd84':'#efe1b8';ctx.fill();
        ctx.strokeStyle='#51554a';ctx.lineWidth=.8;ctx.beginPath();ctx.moveTo(cx+sw*.4,cy-th+7.5*size);ctx.lineTo(cx+sw*.4,cy-th+10*size);ctx.lineTo(cx+sw*.4+2*size,cy-th+11*size);ctx.stroke();
        windowPane(cx+sw*.22,cy-26*size,sw*.31,9*size,night||evening);
        windowPane(cx+sw+8*size,cy-17*size,5*size,8*size,night||evening);
        windowPane(cx+sw+20*size,cy-17*size,5*size,8*size,night||evening);
        // Roadside lamps and soft pools of light are switched by time of day.
        for(let i=0;i<3;i++){
            const x=w*(.66+i*.093),y=h*(.76+Math.sin(i)*.015);
            ctx.strokeStyle=night?'#75857a':'#526652';ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(x,y);ctx.lineTo(x,y-13*size);ctx.stroke();
            ctx.save();if(night||evening){ctx.shadowBlur=10*size;ctx.shadowColor='#ffd28a';}
            ctx.fillStyle=night||evening?'#ffe1a2':'#c1c4a3';ctx.fillRect(x-1.4*size,y-15*size,2.8*size,3*size);ctx.restore();
            if(night||evening){const pool=ctx.createRadialGradient(x,y,0,x,y,14*size);pool.addColorStop(0,wet?'#ffd8912f':'#ffd89118');pool.addColorStop(1,'#ffd89100');ctx.fillStyle=pool;ctx.fillRect(x-15*size,y-15*size,30*size,30*size);}
        }
        ctx.restore();
        // Foreground meadow, with grass or small snow drifts.
        ctx.beginPath();ctx.moveTo(0,h*.88);ctx.bezierCurveTo(w*.23,h*.77,w*.38,h*.95,w*.60,h*.89);ctx.bezierCurveTo(w*.78,h*.82,w*.91,h*.91,w,h*.86);ctx.lineTo(w,h);ctx.lineTo(0,h);ctx.closePath();ctx.fillStyle=snow?(night?'#89a8c0':evening?'#c5bbc9':'#c5dce9'):colors.front;ctx.fill();
        for(let i=0;i<25;i++){
            const x=((i*137.5)%w),y=h*(.9+(i%5)*.016);
            ctx.strokeStyle=snow?'#e9efed2e':(night?'#6c987b22':'#a9c77c38');ctx.lineWidth=snow?1.5:.7;
            ctx.beginPath();ctx.moveTo(x,y);ctx.quadraticCurveTo(x+Math.sin(time*.6+i)*2,y-3,x+3,y-(snow?2:5));ctx.stroke();
        }
        if(spring || autumn){
            ctx.save();ctx.globalAlpha=night?.32:.7;
            for(let i=0;i<24;i++){
                const x=w*((i*.137+.04)%1),y=h*(.79+(i%4)*.009);
                ctx.fillStyle=i%3?colors.leafLight:colors.blossom;
                ctx.beginPath();ctx.ellipse(x,y,spring?1.1:2.2,spring?1.1:.8,i,0,Math.PI*2);ctx.fill();
            }ctx.restore();
        }
        if(!night&&!snow&&!wet&&weather!=='fog'){
            ctx.strokeStyle='#d5e7dc66';ctx.lineWidth=1;
            for(let i=0;i<2;i++){
                const bx=w*(.57+((time*.014+i*.11)%.4)),by=h*(.32+i*.025),flap=Math.sin(time*3+i)*2;
                ctx.beginPath();ctx.moveTo(bx-4,by-flap);ctx.quadraticCurveTo(bx-1,by-2,bx,by);ctx.quadraticCurveTo(bx+1,by-2,bx+4,by-flap);ctx.stroke();
            }
        }
        ctx.restore();
    }
};
