const fs = require('fs');
const data = JSON.parse(fs.readFileSync('c:/Users/Shangqi Yin/Documents/trae_projects/ysq/backend/data/news_data.json', 'utf8'));
console.log('Total items:', data.length);

let js = 'const DEFAULT_NEWS = [\n';
data.forEach((item, i) => {
  js += '            {id:' + item.id + ',title:"' + item.title.replace(/"/g,'\\"') + '",desc:"' + item.desc.replace(/"/g,'\\"') + '",date:"' + item.date + '",author:"' + item.author + '",views:' + item.views + ',status:"' + item.status + '",content:"' + item.content.replace(/"/g,'\\"') + '",images:[]}' + (i < data.length-1 ? ',' : '') + '\n';
});
js += '        ];';

fs.writeFileSync('c:/Users/Shangqi Yin/Documents/trae_projects/ysq/default_news_temp.js', js, 'utf8');
console.log('Generated temp file with', data.length, 'items');
