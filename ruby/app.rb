require 'sinatra'
require 'bundler'
set :protection, except: :ip_spoofing
Bundler.require

#Duomenu baze
require 'json'
svcs = JSON.parse ENV['VCAP_SERVICES']
mysql = svcs.detect { |k,v| k =~ /^mysql/ }.last.first
creds = mysql['credentials']
user, pass, host, name = %w(user password host name).map { |key| creds[key] }
DataMapper.setup(:default, "mysql://#{user}:#{pass}@#{host}/#{name}")

class Puslapiai
    include DataMapper::Resource
    property :id, Serial
    property :pavadinimas, String
    property :trumpas, String
    property :turinys, Text
end

DataMapper.finalize
Puslapiai.auto_upgrade!

get '/' do
  @puslapiai = Puslapiai.all(:order => [:id.desc], :limit => 20)
  erb :duomenys
end

get '/puslapis/:pav' do
  @psl = Puslapiai.first(:trumpas => params[:pav])
  erb :puslapis
end

get '/prideti' do
  erb :forma
end

post '/prideti' do
    slug = params[:pav].downcase.strip.gsub(' ', '-').gsub(/[^\w-]/, '')
    psl = Puslapiai.new(:pavadinimas => params[:pav], :trumpas => slug, :turinys => params[:tur])
    if psl.save
        status 201
        redirect '/'
    else
        status 412
        redirect '/'
    end
end